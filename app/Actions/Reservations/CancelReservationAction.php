<?php

namespace App\Actions\Reservations;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Notifications\CreateUserNotificationAction;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationNotificationService;
use App\Services\ReservationQueueDebugService;
use App\Services\ReservationQueueService;
use App\Support\Notifications\NotificationMessageBuilder;
use App\Support\Notifications\NotificationMetadataBuilder;
use App\Support\Notifications\NotificationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelReservationAction
{
    public function handle(User $actor, Reservation $reservation, ?string $reason = null): Reservation
    {
        if (! $actor->canCancelReservation($reservation)) {
            throw ValidationException::withMessages([
                'reservation' => 'Neturite teisės atšaukti šios rezervacijos.',
            ]);
        }

        if (! $reservation->isActive()) {
            throw ValidationException::withMessages([
                'reservation' => 'Galima atšaukti tik aktyvią rezervaciją.',
            ]);
        }

        $normalizedReason = trim((string) $reason);
        $wasReady = false;
        $pickupBranchId = null;
        $pickupBranchName = null;

        if ($actor->hasAnyEffectiveRole(['administratorius', 'darbuotojas', 'superadministratorius'], $reservation->library_id) && $normalizedReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Nurodykite, kodėl rezervacija atšaukiama.',
            ]);
        }

        $reservation = DB::transaction(function () use ($reservation, $normalizedReason, &$wasReady, &$pickupBranchId, &$pickupBranchName): Reservation {
            $queueService = app(ReservationQueueService::class);

            $reservationContext = Reservation::query()
                ->whereKey($reservation->id)
                ->firstOrFail();

            $queueService->lockQueueContext((int) $reservationContext->library_id, (int) $reservationContext->book_id);

            $lockedReservation = Reservation::query()
                ->with('pickupBranch:id,name')
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasReady = $lockedReservation->isReady();
            $pickupBranchId = $lockedReservation->pickup_branch_id;
            $pickupBranchName = $lockedReservation->pickupBranch?->name;

            $queueService->activeReservationsQuery($lockedReservation->library_id, $lockedReservation->book_id)
                ->lockForUpdate()
                ->get(['id']);

            $positionsBeforeCancellation = $queueService
                ->snapshotPositions($lockedReservation->library_id, $lockedReservation->book_id);

            app(ReservationQueueDebugService::class)->logSnapshot('before_cancellation', $lockedReservation->library_id, $lockedReservation->book_id, [
                'triggering_reservation_id' => $lockedReservation->id,
                'old_positions' => $positionsBeforeCancellation,
            ]);

            $lockedReservation->update([
                'status' => Reservation::STATUS_CANCELLED,
                'pickup_branch_id' => null,
                'assigned_book_copy_id' => null,
                'cancelled_at' => now(),
                'notes' => $normalizedReason !== ''
                    ? trim(implode("\n\n", array_filter([$lockedReservation->notes, 'Atšaukimo priežastis: '.$normalizedReason])))
                    : $lockedReservation->notes,
            ]);

            app(SyncReservationQueueAction::class)->handle($lockedReservation->library_id, $lockedReservation->book_id);

            DB::afterCommit(fn () => app(ReservationNotificationService::class)->notifyQueuePositionsChangedFromSnapshot(
                $lockedReservation->library_id,
                $lockedReservation->book_id,
                $positionsBeforeCancellation
            ));

            app(ReservationQueueDebugService::class)->logSnapshot('after_cancellation', $lockedReservation->library_id, $lockedReservation->book_id, [
                'triggering_reservation_id' => $lockedReservation->id,
                'old_positions' => $positionsBeforeCancellation,
                'new_positions' => $queueService->getPositionsForBook($lockedReservation->library_id, $lockedReservation->book_id),
            ]);

            return $lockedReservation->fresh();
        });

        $reservation->loadMissing(['book:id,slug,title', 'user:id,name,email']);

        app(RecordAuditLogAction::class)->handle(
            $actor,
            'reservation_cancelled',
            $reservation,
            sprintf(
                'Atšaukta rezervacija knygai "%s" nariui %s.',
                $reservation->book?->title ?: 'nežinoma knyga',
                $reservation->user?->name ?: 'nežinomas narys'
            ),
            [
                'reservation_id' => $reservation->id,
                'book_id' => $reservation->book_id,
                'book_title' => $reservation->book?->title,
                'target_member_id' => $reservation->user_id,
                'target_member_name' => $reservation->user?->name,
                'cancel_reason' => $normalizedReason !== '' ? $normalizedReason : null,
            ],
            $reservation->library_id
        );

        if (
            $actor->hasAnyEffectiveRole(['administratorius', 'darbuotojas', 'superadministratorius'], $reservation->library_id)
            && $reservation->user
            && $reservation->user_id !== $actor->id
        ) {
            DB::afterCommit(fn () => app(CreateUserNotificationAction::class)->handle(
                $reservation->user,
                $actor,
                NotificationType::RESERVATION_CANCELLED,
                null,
                NotificationMessageBuilder::reservationCancelled($reservation, $normalizedReason, $wasReady, $pickupBranchName),
                NotificationMetadataBuilder::reservation($reservation, [
                    'reason' => $normalizedReason,
                    'was_ready' => $wasReady,
                    'pickup_branch_id' => $wasReady ? $pickupBranchId : null,
                    'pickup_branch_name' => $wasReady ? $pickupBranchName : null,
                ]),
                Reservation::class,
                $reservation->id
            ));
        }

        return $reservation->fresh();
    }

}
