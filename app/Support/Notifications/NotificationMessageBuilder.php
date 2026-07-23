<?php

namespace App\Support\Notifications;

use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\Reservation;

class NotificationMessageBuilder
{
    public static function reservationCreated(Reservation $reservation, ?int $position, ?string $dueAt, bool $isFirst): string
    {
        $message = sprintf(
            'Jūs sėkmingai rezervavote knygą "%s". Jūsų vieta eilėje: %s.',
            $reservation->book?->title ?: 'nežinoma knyga',
            $position ?: '-'
        );

        if ($dueAt) {
            $message .= ' Šiuo metu knyga paskolinta kitam skaitytojui iki '.$dueAt.'.';
        } elseif ($isFirst) {
            $message .= ' Esate pirmi eilėje, informuosime, kai knygą bus galima pasiimti.';
        } else {
            $message .= ' Knyga šiuo metu nėra paskolinta su žinoma grąžinimo data.';
        }

        if ($reservation->isBranchScoped()) {
            return $message.' Pasirinktas filialas: '.($reservation->branch?->name ?: '-').'.';
        }

        return $message.' Galėsite atsiimti bet kuriame bibliotekos filiale.';
    }

    public static function reservationReady(Reservation $reservation): string
    {
        $message = sprintf(
            'Knyga "%s" jau laukia jūsų.',
            $reservation->book?->title ?: 'nežinoma knyga'
        );

        if ($reservation->pickupBranch) {
            $message .= ' Atsiėmimo filialas: '.$reservation->pickupBranch->name.'.';
        }

        return $message.' Atsiimkite iki '.($reservation->expires_at?->format('Y-m-d H:i') ?: '-').'.';
    }

    public static function reservationQueueChanged(Reservation $reservation, int $position, ?string $dueAt): string
    {
        $message = sprintf(
            'Jūsų rezervacijos eilė pasikeitė. Knyga "%s" - dabar esate %d vietoje eilėje.',
            $reservation->book?->title ?: 'nežinoma knyga',
            $position
        );

        if ($dueAt) {
            return $message.' Dabartinis skaitytojas turi grąžinti knygą iki '.$dueAt.'.';
        }

        if ($position === 1) {
            return $message.' Esate pirmi eilėje.';
        }

        return $message;
    }

    public static function reservationCancelled(Reservation $reservation, string $reason, bool $wasReady, ?string $pickupBranchName): string
    {
        return trim(sprintf(
            'Tavo rezervacija knygai "%s" buvo atšaukta. Priežastis: %s%s',
            $reservation->book?->title ?: 'nežinoma knyga',
            $reason,
            $wasReady && $pickupBranchName ? ' Atsiėmimo filialas buvo '.$pickupBranchName.'.' : ''
        ));
    }

    public static function reservationExpired(Reservation $reservation, ?string $pickupBranchName): string
    {
        return sprintf(
            'Rezervacijos knygai "%s" atsiėmimo terminas baigėsi.%s',
            $reservation->book?->title ?: 'nežinoma knyga',
            $pickupBranchName ? ' Knyga buvo paruošta filiale '.$pickupBranchName.'.' : ''
        );
    }

    public static function reservationFulfilled(BookCopy $bookCopy): string
    {
        return trim(sprintf(
            'Pagal jūsų rezervaciją išduota knyga "%s".%s',
            $bookCopy->book?->title ?: 'nežinoma knyga',
            $bookCopy->branch ? ' Išduota filiale '.$bookCopy->branch->name.'.' : ''
        ));
    }

    public static function bookReturned(BookCopy $bookCopy): string
    {
        return trim(sprintf(
            'Knygos "%s" kopija %s sėkmingai grąžinta.%s',
            $bookCopy->book?->title ?: 'nežinoma knyga',
            $bookCopy->inventory_code ?: ('#'.$bookCopy->id),
            $bookCopy->branch ? ' Grąžinta filiale '.$bookCopy->branch->name.'.' : ''
        ));
    }

    public static function loanOverdue(Loan $loan): string
    {
        return sprintf(
            'Knyga "%s" vėluoja jau %d d. Grąžinimo terminas buvo %s.',
            $loan->bookCopy?->book?->title ?: 'nežinoma knyga',
            $loan->overdue_days,
            $loan->due_at?->format('Y-m-d') ?: '-'
        );
    }
}
