<?php

use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\Reservation;
use Illuminate\Support\Facades\File;

it('keeps database seed and migration files in utf8 Lithuanian text', function () {
    $badEncodingPattern = '/(?:Ã…|Ã„|Ã‚|Ã¢â‚¬)/u';
    $checkedFiles = 0;

    foreach (File::allFiles(database_path()) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $checkedFiles++;
        $contents = File::get($file->getPathname());

        expect($contents)
            ->not
            ->toMatch($badEncodingPattern, $file->getRelativePathname());
    }

    expect($checkedFiles)->toBeGreaterThan(0);
});

it('stores canonical Lithuanian status values in database enum-backed columns', function () {
    $copy = BookCopy::factory()->create([
        'status' => BookCopy::STATUS_LOANED,
        'condition_status' => BookCopy::CONDITION_WORN,
    ]);
    $member = memberInLibrary($copy->library);

    $loan = Loan::factory()->create([
        'library_id' => $copy->library_id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_RETURNED,
        'returned_at' => now(),
    ]);

    $reservation = Reservation::factory()->create([
        'library_id' => $copy->library_id,
        'book_id' => $copy->book_id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_FULFILLED,
        'fulfilled_at' => now(),
    ]);

    expect($copy->refresh()->status)->toBe(BookCopy::STATUS_LOANED);
    expect($copy->condition_status)->toBe(BookCopy::CONDITION_WORN);
    expect($loan->refresh()->status)->toBe(Loan::STATUS_RETURNED);
    expect($reservation->refresh()->status)->toBe(Reservation::STATUS_FULFILLED);
});
