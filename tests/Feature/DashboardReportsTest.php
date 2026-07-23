<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Category;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Publisher;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows extended dashboard reports scoped to the staff library', function () {
    $library = Library::factory()->create(['name' => 'Kalno biblioteka']);
    $otherLibrary = Library::factory()->create(['name' => 'Slenio biblioteka']);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create([
        'library_id' => $library->id,
        'name' => 'Aktyvus narys',
        'membership_number' => 'KAL-MEM-001',
    ]);
    $otherMember = User::factory()->member()->create([
        'library_id' => $otherLibrary->id,
        'name' => 'Kitas narys',
        'membership_number' => 'SLN-MEM-001',
    ]);

    $publisher = Publisher::factory()->create(['name' => 'Testo leidykla']);
    $category = Category::factory()->create(['name' => 'Testine fantastika']);
    $author = Author::factory()->create(['name' => 'Rasa Autore']);
    $popularBook = Book::factory()->create([
        'title' => 'Populiari knyga',
        'publisher_id' => $publisher->id,
        'category_id' => $category->id,
    ]);
    $popularBook->authors()->sync([$author->id]);
    $popularBook->categories()->sync([$category->id]);

    $otherBook = Book::factory()->create(['title' => 'Kita knyga']);

    $activeCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $popularBook->id,
        'status' => BookCopy::STATUS_AVAILABLE,
        'inventory_code' => 'INV-POPULAR-001',
    ]);
    $lostCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $popularBook->id,
        'status' => BookCopy::STATUS_LOST,
    ]);
    $damagedCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $otherBook->id,
        'status' => BookCopy::STATUS_DAMAGED,
    ]);
    $otherLibraryCopy = BookCopy::factory()->create([
        'library_id' => $otherLibrary->id,
        'book_id' => $otherBook->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $activeCopy->id,
        'user_id' => $member->id,
        'status' => 'aktyvi',
        'returned_at' => null,
        'borrowed_at' => now()->subDays(2),
        'due_at' => now()->addDays(5),
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $activeCopy->id,
        'user_id' => $member->id,
        'status' => 'grąžinta',
        'returned_at' => now()->subDay(),
        'borrowed_at' => now()->subDays(10),
        'due_at' => now()->subDays(2),
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $lostCopy->id,
        'user_id' => $member->id,
        'status' => 'vėluoja',
        'returned_at' => null,
        'borrowed_at' => now()->subDays(15),
        'due_at' => now()->subDays(1),
    ]);

    Loan::factory()->create([
        'library_id' => $otherLibrary->id,
        'book_copy_id' => $otherLibraryCopy->id,
        'user_id' => $otherMember->id,
        'status' => 'aktyvi',
        'returned_at' => null,
        'borrowed_at' => now()->subDay(),
        'due_at' => now()->addDays(7),
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $popularBook->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDays(2),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $popularBook->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_FULFILLED,
        'reserved_at' => now()->subDays(5),
        'expires_at' => now()->subDays(4),
        'fulfilled_at' => now()->subDays(4),
        'cancelled_at' => null,
    ]);

    Reservation::factory()->create([
        'library_id' => $otherLibrary->id,
        'book_id' => $otherBook->id,
        'user_id' => $otherMember->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDays(2),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $response = $this->actingAs($staff)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Apžvalga');
    $response->assertDontSee('Bibliotekų palyginimas');
    $response->assertSee('Išdavimų, grąžinimų ir rezervacijų dinamika');
    $response->assertSee('Populiariausios knygos');
    $response->assertSee('Aktyviausi nariai');
    $response->assertSee('Veiklos suvestinė');
    $response->assertSee('Kopijų būsenos');
    $response->assertDontSee('Populiariaušios knygos');
    $response->assertDontSee('Peržiūrėti visą');
    $response->assertDontSee('Peržiūrėti visus');
    $response->assertDontSee('Ctrl + K');
    $response->assertDontSee('Atnaujinti būsenas');
    $response->assertSee(route('manage.book-copies.index', ['status' => BookCopy::STATUS_DAMAGED]), false);
    $response->assertSee('Populiari knyga');
    $response->assertSee('Rasa Autore');
    $response->assertSee('Aktyvus narys');
    $response->assertDontSee('Slenio biblioteka');
    $response->assertDontSee('Kitas narys');
});

it('shows dashboard reports across libraries for super admin', function () {
    $library = Library::factory()->create(['name' => 'Pirma biblioteka']);
    $otherLibrary = Library::factory()->create(['name' => 'Antra biblioteka']);
    $superAdmin = User::factory()->superAdmin()->create();

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'status' => BookCopy::STATUS_LOST,
    ]);

    BookCopy::factory()->create([
        'library_id' => $otherLibrary->id,
        'status' => BookCopy::STATUS_DAMAGED,
    ]);

    $response = $this->actingAs($superAdmin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Apžvalga');
    $response->assertSee('Bibliotekų palyginimas');
    $response->assertSee('Pirma biblioteka');
    $response->assertSee('Antra biblioteka');
});

it('filters dashboard activity by the selected period', function () {
    $library = Library::factory()->create(['name' => 'Miesto biblioteka']);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create([
        'library_id' => $library->id,
        'name' => 'Laikotarpio narys',
        'membership_number' => 'MIE-MEM-001',
    ]);

    $recentBook = Book::factory()->create(['title' => 'Nauja knyga']);
    $oldBook = Book::factory()->create(['title' => 'Sena knyga']);

    $recentCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $recentBook->id,
        'status' => BookCopy::STATUS_AVAILABLE,
        'acquired_at' => now()->subDays(2)->toDateString(),
    ]);

    $oldCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $oldBook->id,
        'status' => BookCopy::STATUS_AVAILABLE,
        'acquired_at' => now()->subDays(45)->toDateString(),
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $recentCopy->id,
        'user_id' => $member->id,
        'status' => 'aktyvi',
        'returned_at' => null,
        'borrowed_at' => now()->subDays(2),
        'due_at' => now()->addDays(7),
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $oldCopy->id,
        'user_id' => $member->id,
        'status' => 'aktyvi',
        'returned_at' => null,
        'borrowed_at' => now()->subDays(45),
        'due_at' => now()->subDays(30),
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $recentBook->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subDay(),
        'expires_at' => now()->addDays(2),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $oldBook->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subDays(40),
        'expires_at' => now()->subDays(35),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $response = $this->actingAs($staff)->get(route('dashboard', [
        'period' => '7_days',
    ]));

    $response->assertOk();
    $response->assertSee('Paskutinės 7 dienos');
    $response->assertSee('Nauja knyga');
    $response->assertDontSee('Sena knyga');
});

it('exports dashboard reports to csv', function () {
    $library = Library::factory()->create(['name' => 'Eksporto biblioteka', 'code' => 'EXP']);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'CSV knyga']);
    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $response = $this->actingAs($staff)->get(route('dashboard.export', ['format' => 'csv']));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $response->assertSee('Suvestinė');
    $response->assertSee('Bibliotekų palyginimas');
});

it('exports dashboard reports to excel format', function () {
    $library = Library::factory()->create(['name' => 'Excel biblioteka', 'code' => 'XLS']);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Excel knyga']);
    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $response = $this->actingAs($staff)->get(route('dashboard.export', ['format' => 'xls']));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
    $response->assertSee('Bibliotekos ataskaita');
    $response->assertSee('Suvestinė');
});





