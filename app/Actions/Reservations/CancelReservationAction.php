<?php

namespace App\Actions\Reservations;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Notifications\CreateUserNotificationAction;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CancelReservationAction
{
    public function handle(User $actor, Reservation $reservation, ?string $reason = null): Reservation
    {
        if ($reservation->library_id !== $actor->library_id) {
            throw ValidationException::withMessages([
                'reservation' => 'Negalite atsaukti kitos bibliotekos rezervacijos.',
            ]);
        }

        if (! $this->canCancel($actor, $reservation)) {
            throw ValidationException::withMessages([
                'reservation' => 'Neturite teises atsaukti sios rezervacijos.',
            ]);
        }

        if (! $reservation->isPending()) {
            throw ValidationException::withMessages([
                'reservation' => 'Galima atsaukti tik laukiancia rezervacija.',
            ]);
        }

        $normalizedReason = trim((string) $reason);

        if (in_array($actor->role, ['admin', 'staff', 'super_admin'], true) && $normalizedReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Nurodykite, kodel rezervacija atsaukiama.',
            ]);
        }

        $reservation->update([
            'status' => Reservation::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'notes' => $normalizedReason !== ''
                ? trim(implode("\n\n", array_filter([$reservation->notes, 'Atsaukimo priezastis: ' . $normalizedReason])))
                : $reservation->notes,
        ]);

        app(SyncReservationQueueAction::class)->handle($reservation->library_id, $reservation->book_id);

        $reservation->loadMissing(['book:id,title', 'user:id,name,email']);

        app(RecordAuditLogAction::class)->handle(
            $actor,
            'reservation_cancelled',
            $reservation,
            sprintf(
                'Atsaukta rezervacija knygai "%s" nariui %s.',
                $reservation->book?->title ?: 'nezinoma knyga',
                $reservation->user?->name ?: 'nezinomas narys'
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
            in_array($actor->role, ['admin', 'staff', 'super_admin'], true)
            && $reservation->user
            && $reservation->user_id !== $actor->id
        ) {
            app(CreateUserNotificationAction::class)->handle(
                $reservation->user,
                $actor,
                'reservation_cancelled',
                'Rezervacija atsaukta',
                sprintf(
                    'Tavo rezervacija knygai "%s" buvo atsaukta. Priezastis: %s',
                    $reservation->book?->title ?: 'nezinoma knyga',
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
        if (in_array($actor->role, ['admin', 'staff', 'super_admin'], true)) {
            return true;
        }

        return $actor->role === 'member' && $reservation->user_id === $actor->id;
    }
}
