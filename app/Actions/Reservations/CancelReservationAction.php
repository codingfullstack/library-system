<?php

namespace App\Actions\Reservations;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Notifications\CreateUserNotificationAction;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationNotificationService;
use App\Services\ReservationQueueDebugService;
use App\Services\ReservationQueueService;
use Illuminate\Validation\ValidationException;

class CancelReservationAction
{
    public function handle(User $actor, Reservation $reservation, ?string $reason = null): Reservation
    {
        if (! $actor->isSuperAdmin() && ! $actor->belongsToLibrary($reservation->library_id)) {
            throw ValidationException::withMessages([
                'reservation' => 'Negalite atšaukti kitos bibliotekos rezervacijos.',
            ]);
        }

        if (! $this->canCancel($actor, $reservation)) {
            throw ValidationException::withMessages([
                'reservation' => 'Neturite teisės atšaukti šios rezervacijos.',
            ]);
        }

        if (! $reservation->isPending()) {
            throw ValidationException::withMessages([
                'reservation' => 'Galima atšaukti tik laukiančią rezervaciją.',
            ]);
        }

        $normalizedReason = trim((string) $reason);

        if ($actor->hasAnyEffectiveRole(['administratorius', 'darbuotojas', 'superadministratorius'], $reservation->library_id) && $normalizedReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Nurodykite, kodėl rezervacija atšaukiama.',
            ]);
        }

        $positionsBeforeCancellation = app(ReservationQueueService::class)
            ->snapshotPositions($reservation->library_id, $reservation->book_id);

        app(ReservationQueueDebugService::class)->logSnapshot('before_cancellation', $reservation->library_id, $reservation->book_id, [
            'triggering_reservation_id' => $reservation->id,
            'old_positions' => $positionsBeforeCancellation,
        ]);

        $reservation->update([
            'status' => Reservation::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'notes' => $normalizedReason !== ''
                ? trim(implode("\n\n", array_filter([$reservation->notes, 'Atšaukimo priežastis: '.$normalizedReason])))
                : $reservation->notes,
        ]);

        app(ReservationNotificationService::class)->notifyQueuePositionsChangedFromSnapshot(
            $reservation->library_id,
            $reservation->book_id,
            $positionsBeforeCancellation
        );

        app(ReservationQueueDebugService::class)->logSnapshot('after_cancellation', $reservation->library_id, $reservation->book_id, [
            'triggering_reservation_id' => $reservation->id,
            'old_positions' => $positionsBeforeCancellation,
            'new_positions' => app(ReservationQueueService::class)->getPositionsForBook($reservation->library_id, $reservation->book_id),
        ]);

        app(SyncReservationQueueAction::class)->handle($reservation->library_id, $reservation->book_id);

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
            app(CreateUserNotificationAction::class)->handle(
                $reservation->user,
                $actor,
                'reservation_cancelled',
                'Rezervacija atšaukta',
                sprintf(
                    'Tavo rezervacija knygai "%s" buvo atšaukta. Priežastis: %s',
                    $reservation->book?->title ?: 'nežinoma knyga',
                    $normalizedReason
                ),
                [
                    'reservation_id' => $reservation->id,
                    'book_id' => $reservation->book_id,
                    'book_title' => $reservation->book?->title,
                    'reason' => $normalizedReason,
                ],
                Reservation::class,
                $reservation->id
            );
        }

        return $reservation->fresh();
    }

    private function canCancel(User $actor, Reservation $reservation): bool
    {
        if ($actor->hasAnyEffectiveRole(['administratorius', 'darbuotojas', 'superadministratorius'], $reservation->library_id)) {
            return true;
        }

        return $actor->effectiveRole($reservation->library_id) === 'narys' && $reservation->user_id === $actor->id;
    }
}
