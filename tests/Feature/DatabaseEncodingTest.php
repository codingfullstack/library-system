<?php

use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\Reservation;
use Illuminate\Support\Facades\File;

it('keeps database seed and migration files in utf8 Lithuanian text', function () {
    $badEncodingPattern = '/(?:Å|Ä|Â|â€)/u';
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
        'condition_status' => 'padėvėta',
    ]);

    $loan = Loan::factory()->create([
        'library_id' => $copy->library_id,
        'book_copy_id' => $copy->id,
        'status' => Loan::STATUS_RETURNED,
        'returned_at' => now(),
    ]);

    $reservation = Reservation::factory()->create([
        'library_id' => $copy->library_id,
        'book_id' => $copy->book_id,
        'status' => Reservation::STATUS_FULFILLED,
        'fulfilled_at' => now(),
    ]);

    expect($copy->refresh()->status)->toBe('išduota');
    expect($copy->condition_status)->toBe('padėvėta');
    expect($loan->refresh()->status)->toBe('grąžinta');
    expect($reservation->refresh()->status)->toBe('įvykdyta');
});
