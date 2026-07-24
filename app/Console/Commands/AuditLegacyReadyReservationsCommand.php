<?php

namespace App\Console\Commands;

use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\Reservation;
use App\Services\ReservationQueueService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditLegacyReadyReservationsCommand extends Command
{
    protected $signature = 'reservations:audit-legacy-ready
        {--apply : Assign deterministic single-copy cases}
        {--maintenance-confirmed : Confirm the operator intentionally runs the repair while the app is in maintenance mode}
        {--json : Emit JSON report}';

    protected $description = 'Audit legacy READY reservations that do not have assigned_book_copy_id.';

    public function handle(): int
    {
        $hasAssignedCopyColumn = Schema::hasColumn('reservations', 'assigned_book_copy_id');

        $rows = Reservation::query()
            ->where('status', Reservation::STATUS_READY)
            ->when($hasAssignedCopyColumn, fn ($query) => $query->whereNull('assigned_book_copy_id'))
            ->orderBy('id')
            ->get();

        $report = $rows->map(fn (Reservation $reservation) => $this->classify($reservation))->values();
        $applied = [];
        $applyError = null;

        if ($this->option('apply')) {
            if (! app()->isDownForMaintenance()) {
                $applyError = 'Apply mode requires Laravel maintenance mode.';
            } elseif (! $this->option('maintenance-confirmed')) {
                $applyError = 'Apply mode requires --maintenance-confirmed.';
            } elseif (! $hasAssignedCopyColumn) {
                $applyError = 'Cannot apply assignments before reservations.assigned_book_copy_id exists.';
            } else {
                foreach ($report->where('category', 'assignable_single_copy') as $item) {
                    $this->applySingleCopyAssignment($item, $applied);
                }
            }
        }

        $payload = [
            'status' => $applyError !== null
                ? 'BLOCK'
                : ($report->whereNotIn('category', ['assignable_single_copy'])->isEmpty() ? 'PASS' : 'WARN'),
            'assigned_book_copy_column_exists' => $hasAssignedCopyColumn,
            'applied_reservation_ids' => $applied,
            'apply_error' => $applyError,
            'categories' => $report->countBy('category')->all(),
            'reservations' => $report->all(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('Legacy READY audit: '.$payload['status']);
            foreach ($payload['categories'] as $category => $count) {
                $this->line("{$category}: {$count}");
            }
        }

        return $applyError === null ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string, mixed>
     */
    private function classify(Reservation $reservation): array
    {
        $candidateIds = $this->candidateCopyQuery($reservation)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->classifyWithCandidateIds($reservation, $candidateIds);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, int>  $applied
     */
    private function applySingleCopyAssignment(array $item, array &$applied): void
    {
        $libraryId = (int) $item['library_id'];
        $bookId = (int) $item['book_id'];

        DB::transaction(function () use ($item, $libraryId, $bookId, &$applied): void {
            app(ReservationQueueService::class)->lockQueueContext($libraryId, $bookId);

            $reservation = Reservation::query()
                ->whereKey((int) $item['reservation_id'])
                ->where('library_id', $libraryId)
                ->where('book_id', $bookId)
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                return;
            }

            $candidateIds = $this->candidateCopyQuery($reservation)
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $classification = $this->classifyWithCandidateIds($reservation, $candidateIds);

            if ($classification['category'] !== 'assignable_single_copy') {
                return;
            }

            $copy = BookCopy::query()
                ->withoutGlobalScope('library')
                ->whereKey($candidateIds[0])
                ->where('library_id', $libraryId)
                ->where('book_id', $bookId)
                ->firstOrFail();

            $reservation->update([
                'assigned_book_copy_id' => $copy->id,
                'pickup_branch_id' => $reservation->pickup_branch_id ?: $copy->branch_id,
            ]);

            $applied[] = $reservation->id;
        });
    }

    private function candidateCopyQuery(Reservation $reservation): Builder
    {
        $candidateQuery = BookCopy::query()
            ->withoutGlobalScope('library')
            ->where('library_id', $reservation->library_id)
            ->where('book_id', $reservation->book_id)
            ->where('status', BookCopy::STATUS_AVAILABLE);

        if ($reservation->pickup_branch_id) {
            $candidateQuery->where('branch_id', $reservation->pickup_branch_id);
        }

        return $candidateQuery;
    }

    /**
     * @param  list<int>  $candidateIds
     * @return array<string, mixed>
     */
    private function classifyWithCandidateIds(Reservation $reservation, array $candidateIds): array
    {
        $candidateIdsWithActiveLoans = collect($candidateIds)
            ->filter(fn (int $copyId): bool => $this->copyHasActiveLoan($copyId))
            ->values()
            ->all();
        $category = 'manual_review_required';
        $reason = 'Manual review required.';

        if (! $reservation->pickup_branch_id) {
            $category = 'missing_pickup_branch';
            $reason = 'READY reservation has no pickup branch.';
        } elseif ($reservation->expires_at && $reservation->expires_at->lte(now())) {
            $category = 'expired';
            $reason = 'READY reservation pickup window is expired.';
        } elseif ($reservation->fulfilled_at !== null || $reservation->status === Reservation::STATUS_FULFILLED) {
            $category = 'already_fulfilled_or_returned';
            $reason = 'Reservation is already fulfilled.';
        } elseif ($candidateIds === []) {
            $category = 'no_available_copy';
            $reason = 'No available copy in the pickup branch.';
        } elseif ($candidateIdsWithActiveLoans !== []) {
            $category = 'active_loan_conflict';
            $reason = 'Candidate copy has an active loan.';
        } elseif (count($candidateIds) === 1) {
            $category = 'assignable_single_copy';
            $reason = 'Exactly one deterministic available copy.';
        } elseif (count($candidateIds) > 1) {
            $category = 'assignable_multiple_candidates';
            $reason = 'Multiple available candidate copies.';
        }

        return [
            'reservation_id' => (int) $reservation->id,
            'user_id' => (int) $reservation->user_id,
            'book_id' => (int) $reservation->book_id,
            'library_id' => (int) $reservation->library_id,
            'pickup_branch_id' => $reservation->pickup_branch_id ? (int) $reservation->pickup_branch_id : null,
            'ready_at' => $reservation->ready_at?->toDateTimeString(),
            'expires_at' => $reservation->expires_at?->toDateTimeString(),
            'candidate_copy_ids' => $candidateIds,
            'candidate_copy_ids_with_active_loans' => $candidateIdsWithActiveLoans,
            'category' => $category,
            'reason' => $reason,
        ];
    }

    private function copyHasActiveLoan(int $copyId): bool
    {
        return Loan::query()
            ->where('book_copy_id', $copyId)
            ->whereNull('returned_at')
            ->whereIn('status', Loan::ACTIVE_STATUSES)
            ->exists();
    }
}
