<?php

use App\Http\Resources\BookCopyDetailsResource;
use App\Http\Resources\BookDetailsResource;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use App\Queries\Books\GetLibraryBookDetailsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('returns backend-owned book availability fields', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/auth/books/'.$book->id)
        ->assertOk()
        ->assertJsonPath('available_copies_count', 1)
        ->assertJsonPath('is_available', true)
        ->assertJsonPath('can_reserve', false)
        ->assertJsonPath('display_status', 'Galima');
});

it('matches can reserve with the reservation action when a copy is available', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $this->actingAs($member)
        ->getJson('/api/auth/books/'.$book->id)
        ->assertOk()
        ->assertJsonPath('can_reserve', false);

    $this->actingAs($member)
        ->postJson('/api/auth/reservations', [
            'book_id' => $book->id,
            'scope' => Reservation::SCOPE_LIBRARY,
        ])
        ->assertUnprocessable();
});

it('keeps current reservation attachment query count stable for book detail copy counts', function (int $copyCount) {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);

    BookCopy::factory()
        ->count($copyCount)
        ->sequence(fn ($sequence) => [
            'inventory_code' => 'INV-'.$copyCount.'-'.$sequence->index,
        ])
        ->create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'status' => BookCopy::STATUS_AVAILABLE,
        ]);

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $preparedBook = app(GetLibraryBookDetailsQuery::class)->handle($admin, $book);
    (new BookDetailsResource($preparedBook))->resolve();

    $queryCount = count(DB::getQueryLog());
    $attachedReservations = $preparedBook->bookCopies
        ->map(fn (BookCopy $copy) => $copy->getAttribute('current_reservation')?->id)
        ->filter()
        ->unique()
        ->values()
        ->all();

    expect($queryCount)->toBeLessThanOrEqual(20)
        ->and($attachedReservations)->toBe([$reservation->id])
        ->and($preparedBook->bookCopies->first()->getAttribute('current_reservation')->queue_position)->toBe(1)
        ->and($preparedBook->bookCopies->first()->getAttribute('current_reservation')->queue_size)->toBe(1);
})->with([1, 5, 25]);

it('does not attach reservations from other libraries or inactive reservations', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $otherMember = User::factory()->member()->create(['library_id' => $otherLibrary->id]);
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $otherBranch = Branch::factory()->create(['library_id' => $otherLibrary->id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    Reservation::factory()->create([
        'library_id' => $otherLibrary->id,
        'book_id' => $book->id,
        'user_id' => $otherMember->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $otherBranch->id,
        'status' => Reservation::STATUS_WAITING,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_CANCELLED,
        'cancelled_at' => now(),
    ]);

    $preparedBook = app(GetLibraryBookDetailsQuery::class)->handle($admin, $book);
    $preparedCopy = $preparedBook->bookCopies->firstWhere('id', $copy->id);

    expect($preparedCopy->getAttribute('current_reservation'))->toBeNull();
});

it('serializes a prepared book copy resource without database queries', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
    ]);

    $prepared = BookCopy::query()
        ->whereKey($copy->id)
        ->with([
            'book:id,slug,title,subtitle,isbn',
            'branch:id,name',
            'location:id,name,room,shelf',
            'statusHistories.user:id,name',
            'activeLoan.user:id,name,email,membership_number',
            'activeLoan.issuer:id,name,email',
            'activeLoan.receiver:id,name,email',
        ])
        ->firstOrFail();

    $request = request();
    $request->setUserResolver(fn () => $admin);

    DB::flushQueryLog();
    DB::enableQueryLog();

    (new BookCopyDetailsResource($prepared, true))->toArray($request);

    expect(DB::getQueryLog())->toBe([]);
});
