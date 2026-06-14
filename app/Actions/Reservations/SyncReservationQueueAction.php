<?php

namespace App\Actions\Reservations;

use App\Models\Reservation;
use App\Services\ReservationNotificationService;
use App\Services\ReservationQueueService;
use Illuminate\Database\Eloquent\Builder;

class SyncReservationQueueAction
{
    private const DEFAULT_WINDOW_DAYS = 14;

    public function __construct(
        private readonly ReservationQueueService $queueService,
        private readonly ReservationNotificationService $notificationService,
    ) {}

    public function handle(int $libraryId, int $bookId): void
    {
        $this->expireElapsedReservations($libraryId, $bookId);

        $this->syncQueue($libraryId, $bookId, Reservation::SCOPE_LIBRARY, null);

        Reservation::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $bookId)
            ->where('scope', Reservation::SCOPE_BRANCH)
            ->pending()
            ->whereNotNull('branch_id')
            ->distinct()
            ->pluck('branch_id')
            ->each(fn ($branchId) => $this->syncQueue($libraryId, $bookId, Reservation::SCOPE_BRANCH, (int) $branchId));
    }

    private function syncQueue(int $libraryId, int $bookId, string $scope, ?int $branchId): void
    {
        $pendingReservations = $this->queueService->pendingReservations($libraryId, $bookId, $scope, $branchId);
        $firstReservation = $pendingReservations->first();

        if (! $firstReservation) {
            return;
        }

        if (! $this->queueService->hasAvailableCopies($libraryId, $bookId, $scope, $branchId)) {
            $this->pendingReservationsQuery($libraryId, $bookId, $scope, $branchId)
                ->whereNotNull('expires_at')
                ->update(['expires_at' => null]);

            $pendingReservations->each(
                fn (Reservation $reservation) => $this->notificationService->notifyQueuePositionChanged($reservation)
            );

            return;
        }

        if ($firstReservation->expires_at === null || $firstReservation->expires_at->isPast()) {
            $firstReservation = Reservation::query()
                ->with(['user:id,name,email', 'book:id,slug,title'])
                ->findOrFail($firstReservation->id);

            $firstReservation->update([
                'expires_at' => now()->addDays(self::DEFAULT_WINDOW_DAYS),
            ]);

            $firstReservation->refresh();
            $firstReservation->setAttribute('queue_position', 1);

            $this->notificationService->notifyReady($firstReservation);
        }

        $this->pendingReservationsQuery($libraryId, $bookId, $scope, $branchId)
            ->whereKeyNot($firstReservation->id)
            ->whereNotNull('expires_at')
            ->update(['expires_at' => null]);

        $pendingReservations->each(
            fn (Reservation $reservation) => $this->notificationService->notifyQueuePositionChanged($reservation)
        );
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

    private function pendingReservationsQuery(int $libraryId, int $bookId, string $scope, ?int $branchId): Builder
    {
        return $this->queueService->pendingReservationsQuery($libraryId, $bookId, $scope, $branchId);
    }
}
