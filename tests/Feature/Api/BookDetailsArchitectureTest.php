<?php

use App\Http\Resources\BookCopyDetailsResource;
use App\Http\Resources\BookDetailsResource;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\Location;
use App\Models\Loan;
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

it('scopes book details availability and categories to the requested active library context', function () {
    $firstLibrary = Library::factory()->create();
    $secondLibrary = Library::factory()->create();
    $firstBranch = Branch::factory()->create(['library_id' => $firstLibrary->id]);
    $secondBranch = Branch::factory()->create(['library_id' => $secondLibrary->id]);
    $member = memberInLibrary($firstLibrary);

    LibraryMembership::query()->create([
        'library_id' => $secondLibrary->id,
        'user_id' => $member->id,
        'membership_number' => $member->membership_number,
        'is_active' => true,
        'joined_at' => now()->addMinute(),
    ]);

    $book = Book::factory()->create([
        'isbn' => '9786094799716',
        'category_id' => null,
    ]);
    $romanai = Category::factory()->create(['name' => 'Romanai']);
    $meilesRomanai = Category::factory()->create(['name' => 'Meilės romanai']);
    $book->categories()->sync([$romanai->id, $meilesRomanai->id]);

    BookCopy::factory()->create([
        'library_id' => $firstLibrary->id,
        'book_id' => $book->id,
        'branch_id' => $firstBranch->id,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);
    BookCopy::factory()->create([
        'library_id' => $secondLibrary->id,
        'book_id' => $book->id,
        'branch_id' => $secondBranch->id,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);

    Reservation::factory()->create([
        'library_id' => $firstLibrary->id,
        'book_id' => $book->id,
        'user_id' => memberInLibrary($firstLibrary)->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'ready_at' => null,
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $this->actingAs($member)
        ->withHeader('X-Library-Id', (string) $firstLibrary->id)
        ->getJson('/api/auth/books/'.$book->id)
        ->assertOk()
        ->assertJsonPath('copies_count', 1)
        ->assertJsonPath('available_copies_count', 1)
        ->assertJsonPath('availability_status', 'available')
        ->assertJsonPath('display_status', 'Galima')
        ->assertJsonPath('is_available', true)
        ->assertJsonPath('has_reservation_queue', true)
        ->assertJsonPath('has_waiting_queue', true)
        ->assertJsonPath('waiting_reservations_count', 1)
        ->assertJsonPath('current_user_reservation', null)
        ->assertJsonPath('categories.0.name', 'Romanai')
        ->assertJsonPath('categories.1.name', 'Meilės romanai');

    $this->actingAs($member)
        ->withHeader('X-Library-Id', (string) $secondLibrary->id)
        ->getJson('/api/auth/books/'.$book->id)
        ->assertOk()
        ->assertJsonPath('copies_count', 1)
        ->assertJsonPath('available_copies_count', 1)
        ->assertJsonPath('availability_status', 'available')
        ->assertJsonPath('display_status', 'Galima')
        ->assertJsonPath('is_available', true)
        ->assertJsonPath('has_reservation_queue', false)
        ->assertJsonCount(2, 'categories');
});

it('returns branch availability using canonical copy availability rules', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $member = memberInLibrary($library);
    $loanedMember = memberInLibrary($library);
    $readyMember = memberInLibrary($library);
    $book = Book::factory()->create();
    $center = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Centras']);
    $youth = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Jaunimo skyrius']);
    $otherBranch = Branch::factory()->create(['library_id' => $otherLibrary->id, 'name' => 'Kita biblioteka']);

    $availableCenterCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $center->id,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
    $loanedCenterCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $center->id,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
    $readyCenterCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $center->id,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $youth->id,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
    BookCopy::factory()->create([
        'library_id' => $otherLibrary->id,
        'book_id' => $book->id,
        'branch_id' => $otherBranch->id,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $loanedCenterCopy->id,
        'user_id' => $loanedMember->id,
        'status' => 'aktyvi',
        'returned_at' => null,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $readyMember->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'pickup_branch_id' => $center->id,
        'assigned_book_copy_id' => $readyCenterCopy->id,
        'status' => Reservation::STATUS_READY,
        'reserved_at' => now()->subDay(),
        'ready_at' => now()->subHour(),
        'expires_at' => now()->addDays(7),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $response = $this->actingAs($member)
        ->withHeader('X-Library-Id', (string) $library->id)
        ->getJson('/api/auth/books/'.$book->id)
        ->assertOk()
        ->assertJsonPath('copies_count', 4)
        ->assertJsonPath('available_copies_count', 2)
        ->assertJsonPath('branch_availability.0.branch_id', $center->id)
        ->assertJsonPath('branch_availability.0.branch_name', 'Centras')
        ->assertJsonPath('branch_availability.0.total_copies_count', 3)
        ->assertJsonPath('branch_availability.0.available_copies_count', 1)
        ->assertJsonPath('branch_availability.1.branch_id', $youth->id)
        ->assertJsonPath('branch_availability.1.branch_name', 'Jaunimo skyrius')
        ->assertJsonPath('branch_availability.1.total_copies_count', 1)
        ->assertJsonPath('branch_availability.1.available_copies_count', 1);

    expect(collect($response->json('branch_availability'))->pluck('branch_id')->all())
        ->not->toContain($otherBranch->id)
        ->and($availableCenterCopy->exists)->toBeTrue();
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
            'activeReadyReservation:id,library_id,book_id,user_id,assigned_book_copy_id,status,scope,branch_id,pickup_branch_id,report_branch_id,reserved_at,ready_at,expires_at,fulfilled_at,cancelled_at',
        ])
        ->firstOrFail();

    $request = request();
    $request->setUserResolver(fn () => $admin);

    DB::flushQueryLog();
    DB::enableQueryLog();

    (new BookCopyDetailsResource($prepared, true))->toArray($request);

    expect(DB::getQueryLog())->toBe([]);
});
