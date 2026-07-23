<?php

namespace App\Support\Notifications;

use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\Reservation;

class NotificationMetadataBuilder
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function reservation(Reservation $reservation, array $extra = []): array
    {
        $reservation->loadMissing(['book:id,title', 'branch:id,name', 'pickupBranch:id,name']);

        return self::compact(array_merge([
            'reservation_id' => $reservation->id,
            'book_id' => $reservation->book_id,
            'book_title' => $reservation->book?->title,
            'library_id' => $reservation->library_id,
            'scope' => $reservation->scope,
            'branch_id' => $reservation->branch_id,
            'branch_name' => $reservation->isBranchScoped() ? $reservation->branch?->name : null,
            'pickup_branch_id' => $reservation->pickup_branch_id,
            'pickup_branch_name' => $reservation->pickupBranch?->name,
            'ready_at' => $reservation->ready_at?->toDateTimeString(),
            'expires_at' => $reservation->expires_at?->toDateTimeString(),
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function loan(Loan $loan, array $extra = []): array
    {
        $loan->loadMissing(['bookCopy:id,book_id,branch_id,inventory_code', 'bookCopy.book:id,title', 'bookCopy.branch:id,name']);
        $bookCopy = $loan->bookCopy;

        return self::compact(array_merge([
            'loan_id' => $loan->id,
            'book_id' => $bookCopy?->book_id,
            'book_title' => $bookCopy?->book?->title,
            'copy_id' => $bookCopy?->id,
            'copy_inventory_code' => $bookCopy?->inventory_code,
            'branch_id' => $bookCopy?->branch_id,
            'branch_name' => $bookCopy?->branch?->name,
            'due_at' => $loan->due_at?->toDateString(),
            'returned_at' => $loan->returned_at?->toDateTimeString(),
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function bookCopy(BookCopy $bookCopy, array $extra = []): array
    {
        $bookCopy->loadMissing(['book:id,title', 'branch:id,name']);

        return self::compact(array_merge([
            'book_id' => $bookCopy->book_id,
            'book_title' => $bookCopy->book?->title,
            'copy_id' => $bookCopy->id,
            'copy_inventory_code' => $bookCopy->inventory_code,
            'branch_id' => $bookCopy->branch_id,
            'branch_name' => $bookCopy->branch?->name,
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function compact(array $metadata): array
    {
        return collect($metadata)
            ->reject(fn ($value) => $value === null)
            ->all();
    }
}
