<?php

namespace App\Actions\Reservations;

use App\Models\BookCopy;
use App\Models\Reservation;
use App\Services\ReservationNotificationService;
use App\Services\ReservationQueueDebugService;
use App\Services\ReservationQueueService;
use App\Support\Observability\OperationDiagnostics;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SyncReservationQueueAction
{
    private const DEFAULT_WINDOW_DAYS = 14;

    public function __construct(
        private readonly ReservationQueueService $queueService,
        private readonly ReservationNotificationService $notificationService,
    ) {}

    public function handle(int $libraryId, int $bookId): void
    {
        try {
            DB::transaction(function () use ($libraryId, $bookId): void {
                $this->queueService->lockQueueContext($libraryId, $bookId);

                $positionsBefore = $this->queueService->snapshotPositions($libraryId, $bookId);

                app(ReservationQueueDebugService::class)->logSnapshot('before_queue_sync', $libraryId, $bookId, [
                    'old_positions' => $positionsBefore,
                ]);

                $this->syncQueue($libraryId, $bookId);

                DB::afterCommit(function () use ($libraryId, $bookId, $positionsBefore): void {
                    $this->notificationService->notifyQueuePositionsChangedFromSnapshot(
                        $libraryId,
                        $bookId,
                        $positionsBefore
                    );
                });

                app(ReservationQueueDebugService::class)->logSnapshot('after_queue_sync', $libraryId, $bookId, [
                    'old_positions' => $positionsBefore,
                    'new_positions' => $this->queueService->getPositionsForBook($libraryId, $bookId),
                ]);
            });
        } catch (Throwable $exception) {
            app(OperationDiagnostics::class)->failure('reservation_queue_sync_failed', $exception, [
                'operation' => 'reservation_queue_sync',
                'library_id' => $libraryId,
                'book_id' => $bookId,
            ]);

            throw $exception;
        }
    }

    private function syncQueue(int $libraryId, int $bookId): void
    {
        $lockedReservations = $this->queueService
            ->activeReservationsQuery($libraryId, $bookId)
            ->lockForUpdate()
            ->get();

        if ($lockedReservations->isEmpty()) {
            return;
        }

        $availableCopies = $this->queueService
            ->availableCopiesQuery($libraryId, $bookId)
            ->whereNotNull('branch_id')
            ->orderBy('branch_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($availableCopies->isEmpty()) {
            return;
        }

        $servedReservationIds = [];

        foreach ($availableCopies as $copy) {
            $reservation = $this->eligibleLockedReservationForCopy($lockedReservations, $copy, $servedReservationIds);

            if (! $reservation) {
                continue;
            }

            $servedReservationIds[] = (int) $reservation->id;

            if (! $reservation->isPending()) {
                continue;
            }

            app(ReservationQueueDebugService::class)->logSnapshot('before_ready_assignment', $libraryId, $bookId, [
                'triggering_reservation_id' => $reservation->id,
                'triggering_copy_id' => $copy->id,
                'triggering_branch_id' => (int) $copy->branch_id,
                'global_position' => $this->queueService->getQueuePosition($reservation),
            ]);

            $readyAttributes = [
                'status' => Reservation::STATUS_READY,
                'pickup_branch_id' => (int) $copy->branch_id,
                'report_branch_id' => (int) $copy->branch_id,
                'assigned_book_copy_id' => (int) $copy->id,
                'ready_at' => now(),
                'expires_at' => now()->addDays(self::DEFAULT_WINDOW_DAYS),
            ];

            try {
                $this->assertReadyAssignmentInvariant($reservation, $copy, $readyAttributes);
                if ((int) $readyAttributes['report_branch_id'] !== (int) $readyAttributes['pickup_branch_id']) {
                    throw ValidationException::withMessages([
                        'reservation' => ['Rezervacijos ataskaitinis filialas turi sutapti su atsiėmimo filialu.'],
                    ]);
                }

                $reservation->update($readyAttributes);
            } catch (QueryException $exception) {
                if (! $this->isActiveReadyCopyUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                throw ValidationException::withMessages([
                    'reservation' => ['Si kopija jau priskirta kitai paruostai rezervacijai. Pakartokite eiles sinchronizavima.'],
                ]);
            }

            $readyReservation = Reservation::query()
                ->with(['user:id,name,email', 'book:id,slug,title'])
                ->findOrFail($reservation->id);

            DB::afterCommit(fn () => $this->notificationService->notifyReady($readyReservation));

            app(ReservationQueueDebugService::class)->logSnapshot('after_ready_assignment', $libraryId, $bookId, [
                'triggering_reservation_id' => $reservation->id,
                'triggering_copy_id' => $copy->id,
                'triggering_branch_id' => (int) $copy->branch_id,
                'global_position' => $this->queueService->getQueuePosition($reservation),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $readyAttributes
     */
    private function assertReadyAssignmentInvariant(Reservation $reservation, BookCopy $copy, array $readyAttributes): void
    {
        $assignedCopyId = $readyAttributes['assigned_book_copy_id'] ?? null;
        $pickupBranchId = $readyAttributes['pickup_branch_id'] ?? null;
        $reportBranchId = $readyAttributes['report_branch_id'] ?? null;

        if ($assignedCopyId === null || $pickupBranchId === null || $reportBranchId === null) {
            throw ValidationException::withMessages([
                'reservation' => ['Paruostai rezervacijai privaloma priskirti kopija, atsiemimo filiala ir ataskaitini filiala.'],
            ]);
        }

        if ((int) $assignedCopyId !== (int) $copy->id || (int) $copy->library_id !== (int) $reservation->library_id) {
            throw ValidationException::withMessages([
                'reservation' => ['Rezervacijai priskiriama kopija turi priklausyti tai paciai bibliotekai.'],
            ]);
        }

        if ((int) $copy->branch_id !== (int) $pickupBranchId || (int) $pickupBranchId !== (int) $reportBranchId) {
            throw ValidationException::withMessages([
                'reservation' => ['Rezervacijos ataskaitinis filialas turi sutapti su atsiemimo filialu ir kopijos filialu.'],
            ]);
        }
    }

    /**
     * @param  Collection<int, Reservation>  $lockedReservations
     * @param  array<int, int>  $exceptReservationIds
     */
    private function eligibleLockedReservationForCopy(Collection $lockedReservations, BookCopy $copy, array $exceptReservationIds): ?Reservation
    {
        $excluded = array_fill_keys(array_map('intval', $exceptReservationIds), true);

        return $lockedReservations->first(function (Reservation $reservation) use ($copy, $excluded): bool {
            if (isset($excluded[(int) $reservation->id])) {
                return false;
            }

            if (! $this->queueService->canBeServedByBranch($reservation, (int) $copy->branch_id)) {
                return false;
            }

            if ($reservation->status === Reservation::STATUS_WAITING) {
                return true;
            }

            return $reservation->status === Reservation::STATUS_READY
                && (int) $reservation->assigned_book_copy_id === (int) $copy->id;
        });
    }

    private function isActiveReadyCopyUniqueConstraintViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'reservations_active_ready_book_copy_unique')
            || str_contains($message, 'reservations.active_ready_book_copy_id')
            || str_contains($message, 'active_ready_book_copy_id');
    }
}
