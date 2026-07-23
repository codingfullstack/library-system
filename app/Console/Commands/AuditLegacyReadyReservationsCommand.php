<?php

namespace App\Console\Commands;

use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditLegacyReadyReservationsCommand extends Command
{
    protected $signature = 'reservations:audit-legacy-ready {--apply : Assign deterministic single-copy cases} {--json : Emit JSON report}';

    protected $description = 'Audit legacy READY reservations that do not have assigned_book_copy_id.';

    public function handle(): int
    {
        $rows = Reservation::query()
            ->where('status', Reservation::STATUS_READY)
            ->whereNull('assigned_book_copy_id')
            ->orderBy('id')
            ->get();

        $report = $rows->map(fn (Reservation $reservation) => $this->classify($reservation))->values();
        $applied = [];

        if ($this->option('apply')) {
            foreach ($report->where('category', 'assignable_single_copy') as $item) {
                DB::transaction(function () use ($item, &$applied): void {
                    $reservation = Reservation::query()->whereKey($item['reservation_id'])->lockForUpdate()->firstOrFail();
                    $copy = BookCopy::query()->whereKey($item['candidate_copy_ids'][0])->lockForUpdate()->firstOrFail();

                    if ($this->classify($reservation)['category'] !== 'assignable_single_copy') {
                        return;
                    }

                    $reservation->update([
                        'assigned_book_copy_id' => $copy->id,
                        'pickup_branch_id' => $reservation->pickup_branch_id ?: $copy->branch_id,
                    ]);

                    $applied[] = $reservation->id;
                });
            }
        }

        $payload = [
            'status' => $report->whereNotIn('category', ['assignable_single_copy'])->isEmpty() ? 'PASS' : 'WARN',
            'applied_reservation_ids' => $applied,
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

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function classify(Reservation $reservation): array
    {
        $candidateQuery = BookCopy::query()
            ->withoutGlobalScope('library')
            ->where('library_id', $reservation->library_id)
            ->where('book_id', $reservation->book_id)
            ->where('status', BookCopy::STATUS_AVAILABLE);

        if ($reservation->pickup_branch_id) {
            $candidateQuery->where('branch_id', $reservation->pickup_branch_id);
        }

        $candidateIds = $candidateQuery->pluck('id')->map(fn ($id): int => (int) $id)->all();
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
