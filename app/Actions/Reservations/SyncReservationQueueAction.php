<?php

namespace App\Actions\Reservations;

use App\Models\Reservation;
use App\Services\ReservationNotificationService;
use App\Services\ReservationQueueDebugService;
use App\Services\ReservationQueueService;
use App\Models\BookCopy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncReservationQueueAction
{
    private const DEFAULT_WINDOW_DAYS = 14;

    public function __construct(
        private readonly ReservationQueueService $queueService,
        private readonly ReservationNotificationService $notificationService,
    ) {}

    public function handle(int $libraryId, int $bookId): void
    {
        DB::transaction(function () use ($libraryId, $bookId): void {
            $positionsBeforeExpiration = $this->queueService->snapshotPositionsBeforeExpiringElapsed($libraryId, $bookId);

            app(ReservationQueueDebugService::class)->logSnapshot('before_expiration', $libraryId, $bookId, [
                'old_positions' => $positionsBeforeExpiration,
            ]);

            $this->expireElapsedReservations($libraryId, $bookId);

            $this->notificationService->notifyQueuePositionsChangedFromSnapshot(
                $libraryId,
                $bookId,
                $positionsBeforeExpiration
            );

            app(ReservationQueueDebugService::class)->logSnapshot('after_expiration', $libraryId, $bookId, [
                'old_positions' => $positionsBeforeExpiration,
                'new_positions' => $this->queueService->getPositionsForBook($libraryId, $bookId),
            ]);

            $this->syncQueue($libraryId, $bookId);
        });
    }

    private function syncQueue(int $libraryId, int $bookId): void
    {
        $pendingReservations = $this->queueService
            ->pendingReservationsQuery($libraryId, $bookId)
            ->with(['user:id,name,email', 'book:id,slug,title'])
            ->lockForUpdate()
            ->get()
            ->values()
            ->map(function (Reservation $reservation, int $index) {
                $reservation->setAttribute('queue_position', $index + 1);

                return $reservation;
            });

        $availableCopiesByBranch = BookCopy::query()
            ->withoutGlobalScope('library')
            ->where('library_id', $libraryId)
            ->where('book_id', $bookId)
            ->where('status', BookCopy::STATUS_AVAILABLE)
            ->whereNotNull('branch_id')
            ->selectRaw('branch_id, COUNT(*) as copies_count')
            ->groupBy('branch_id')
            ->orderBy('branch_id')
            ->pluck('copies_count', 'branch_id');

        if ($pendingReservations->isEmpty()) {
            return;
        }

        if ($availableCopiesByBranch->isEmpty()) {
            return;
        }

        $readyReservationIds = [];

        foreach ($availableCopiesByBranch as $branchId => $copyCount) {
            for ($copyIndex = 0; $copyIndex < (int) $copyCount; $copyIndex++) {
                $readyReservation = $this->queueService
                    ->firstWaitingForAssignmentIfServiceableByBranch($libraryId, $bookId, (int) $branchId);

                if (! $readyReservation) {
                    break;
                }

                app(ReservationQueueDebugService::class)->logSnapshot('before_ready_assignment', $libraryId, $bookId, [
                    'triggering_reservation_id' => $readyReservation->id,
                    'triggering_branch_id' => (int) $branchId,
                    'available_copy_index' => $copyIndex + 1,
                    'serviceable_index' => $copyIndex + 1,
                    'global_position' => $this->queueService->getQueuePosition($readyReservation),
                ]);

                $readyReservationIds[] = (int) $readyReservation->id;
                $position = $pendingReservations->search(fn (Reservation $reservation) => (int) $reservation->id === (int) $readyReservation->id);
                $currentPosition = $this->queueService->getQueuePosition($readyReservation);

                if ($currentPosition !== 1 || $this->queueService->hasWaitingReservationBefore($readyReservation)) {
                    Log::warning('Attempted to prepare non-first reservation', [
                        'reservation_id' => $readyReservation->id,
                        'position' => $currentPosition,
                        'library_id' => $libraryId,
                        'book_id' => $bookId,
                        'branch_id' => (int) $branchId,
                    ]);

                    break;
                }

                $readyReservation = Reservation::query()
                    ->with(['user:id,name,email', 'book:id,slug,title'])
                    ->findOrFail($readyReservation->id);

                $readyReservation->update([
                    'expires_at' => now()->addDays(self::DEFAULT_WINDOW_DAYS),
                ]);

                $readyReservation->refresh();
                $readyReservation->setAttribute('queue_position', $position === false ? $this->queueService->positionFor($readyReservation) : $position + 1);

                $this->notificationService->notifyReady($readyReservation);

                app(ReservationQueueDebugService::class)->logSnapshot('after_ready_assignment', $libraryId, $bookId, [
                    'triggering_reservation_id' => $readyReservation->id,
                    'triggering_branch_id' => (int) $branchId,
                    'serviceable_index' => $copyIndex + 1,
                    'global_position' => $this->queueService->getQueuePosition($readyReservation),
                ]);
            }
        }

    }

    private function expireElapsedReservations(int $libraryId, int $bookId): void
    {
        Reservation::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $bookId)
            ->where('status', Reservation::STATUS_RESERVED)
            ->whereNull('fulfilled_at')
            ->whereNull('cancelled_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => Reservation::STATUS_EXPIRED]);
    }
}
