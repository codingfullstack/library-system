<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('treats an in-circulation copy without loan or ready reservation as operationally free', function () {
    $library = Library::factory()->create();
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);

    expect($copy->fresh()->operationalStatus())->toBe('laisva')
        ->and(BookCopy::query()->whereKey($copy->id)->operationallyAvailable()->exists())->toBeTrue();
});

it('keeps web list, web detail and api availability consistent for the Y library scenario', function () {
    ['book' => $book, 'libraryY' => $libraryY] = availabilityScenario();
    $member = memberInLibrary($libraryY);
    $admin = adminInLibrary($libraryY);

    $this->actingAs($admin)
        ->get('/knygos?search=9786094799716')
        ->assertOk()
        ->assertSee('Galima')
        ->assertSee('1');

    $this->actingAs($member)
        ->get('/knygos?search=9786094799716')
        ->assertOk()
        ->assertSee('Galima');

    $this->actingAs($member)
        ->get('/knygos/'.$book->slug)
        ->assertOk()
        ->assertSee('Galima')
        ->assertSee('Yra laisvų kopijų: 1')
        ->assertDontSee('Šiai knygai yra rezervacijų eilė.');

    $this->actingAs($member)
        ->getJson('/api/auth/books/'.$book->id)
        ->assertOk()
        ->assertJsonPath('total_copies_count', 4)
        ->assertJsonPath('available_copies_count', 1)
        ->assertJsonPath('active_reservations_count', 2)
        ->assertJsonPath('ready_reservations_count', 2)
        ->assertJsonPath('waiting_reservations_count', 0)
        ->assertJsonPath('has_waiting_queue', false)
        ->assertJsonPath('availability_status', 'available')
        ->assertJsonPath('availability_label', 'Galima')
        ->assertJsonPath('current_user_reservation', null)
        ->assertJsonCount(2, 'categories');
});

it('isolates the same book availability by library and separates ready from waiting reservations', function () {
    ['book' => $book, 'libraryX' => $libraryX, 'libraryY' => $libraryY] = availabilityScenario();

    $this->actingAs(memberInLibrary($libraryX))
        ->getJson('/api/auth/books/'.$book->id)
        ->assertOk()
        ->assertJsonPath('total_copies_count', 4)
        ->assertJsonPath('available_copies_count', 0)
        ->assertJsonPath('active_reservations_count', 4)
        ->assertJsonPath('ready_reservations_count', 3)
        ->assertJsonPath('waiting_reservations_count', 1)
        ->assertJsonPath('has_waiting_queue', true)
        ->assertJsonPath('availability_status', 'unavailable')
        ->assertJsonPath('availability_label', 'Neprieinama');

    $this->actingAs(memberInLibrary($libraryY))
        ->getJson('/api/auth/books/'.$book->id)
        ->assertOk()
        ->assertJsonPath('available_copies_count', 1)
        ->assertJsonPath('active_reservations_count', 2)
        ->assertJsonPath('ready_reservations_count', 2)
        ->assertJsonPath('waiting_reservations_count', 0)
        ->assertJsonPath('availability_status', 'available');
});

it('excludes loaned, ready-assigned and unavailable lifecycle copies from free counts', function () {
    $library = Library::factory()->create();
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $member = memberInLibrary($library);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);
    $loaned = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);
    $ready = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);

    foreach ([BookCopy::STATUS_LOST, BookCopy::STATUS_WITHDRAWN, BookCopy::STATUS_MAINTENANCE, BookCopy::STATUS_PREPARING] as $status) {
        BookCopy::factory()->create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'status' => $status,
            'lifecycle_status' => $status,
        ]);
    }

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $loaned->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => User::factory()->member()->create(['library_id' => $library->id])->id,
        'status' => Reservation::STATUS_READY,
        'assigned_book_copy_id' => $ready->id,
        'pickup_branch_id' => $branch->id,
    ]);

    $this->actingAs($member)
        ->getJson('/api/auth/books/'.$book->id)
        ->assertOk()
        ->assertJsonPath('total_copies_count', 7)
        ->assertJsonPath('available_copies_count', 1);
});

it('does not expose a waiting reservation as eligible for a lost copy', function () {
    $library = Library::factory()->create();
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $lostCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'status' => BookCopy::STATUS_LOST,
        'lifecycle_status' => BookCopy::STATUS_LOST,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => memberInLibrary($library)->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
    ]);

    expect(app(ReservationQueueService::class)->getEligibleReservationForCopy($lostCopy))->toBeNull();
});

function availabilityScenario(): array
{
    $libraryX = Library::factory()->create(['name' => 'Vilniaus miesto centrinė biblioteka']);
    $libraryY = Library::factory()->create(['name' => 'Kauno rajono viešoji biblioteka']);
    $branchX = Branch::factory()->create(['library_id' => $libraryX->id]);
    $branchY = Branch::factory()->create(['library_id' => $libraryY->id]);
    $book = Book::factory()->create([
        'title' => 'Lapkričio 9',
        'isbn' => '9786094799716',
        'category_id' => null,
    ]);
    $romanai = Category::factory()->create(['name' => 'Romanai']);
    $meilesRomanai = Category::factory()->create(['name' => 'Meilės romanai']);
    $book->categories()->sync([$romanai->id, $meilesRomanai->id]);

    $xReadyCopies = BookCopy::factory()->count(3)->create([
        'library_id' => $libraryX->id,
        'book_id' => $book->id,
        'branch_id' => $branchX->id,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);
    BookCopy::factory()->create([
        'library_id' => $libraryX->id,
        'book_id' => $book->id,
        'branch_id' => $branchX->id,
        'status' => BookCopy::STATUS_LOST,
        'lifecycle_status' => BookCopy::STATUS_LOST,
    ]);

    foreach ($xReadyCopies as $copy) {
        Reservation::factory()->create([
            'library_id' => $libraryX->id,
            'book_id' => $book->id,
            'user_id' => memberInLibrary($libraryX)->id,
            'status' => Reservation::STATUS_READY,
            'assigned_book_copy_id' => $copy->id,
            'pickup_branch_id' => $branchX->id,
        ]);
    }
    Reservation::factory()->create([
        'library_id' => $libraryX->id,
        'book_id' => $book->id,
        'user_id' => memberInLibrary($libraryX)->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
    ]);

    $yReadyCopies = BookCopy::factory()->count(2)->create([
        'library_id' => $libraryY->id,
        'book_id' => $book->id,
        'branch_id' => $branchY->id,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);
    BookCopy::factory()->create([
        'library_id' => $libraryY->id,
        'book_id' => $book->id,
        'branch_id' => $branchY->id,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);
    BookCopy::factory()->create([
        'library_id' => $libraryY->id,
        'book_id' => $book->id,
        'branch_id' => $branchY->id,
        'status' => BookCopy::STATUS_LOST,
        'lifecycle_status' => BookCopy::STATUS_LOST,
    ]);

    foreach ($yReadyCopies as $copy) {
        Reservation::factory()->create([
            'library_id' => $libraryY->id,
            'book_id' => $book->id,
            'user_id' => memberInLibrary($libraryY)->id,
            'status' => Reservation::STATUS_READY,
            'assigned_book_copy_id' => $copy->id,
            'pickup_branch_id' => $branchY->id,
        ]);
    }

    return compact('book', 'libraryX', 'libraryY');
}
