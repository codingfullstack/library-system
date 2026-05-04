<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Library;
use App\Models\Location;
use App\Models\Loan;
use App\Models\Publisher;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters books by author category publisher and availability', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);

    $matchingAuthor = Author::factory()->create(['name' => 'Tinkamas Autorius']);
    $otherAuthor = Author::factory()->create(['name' => 'Kitas Autorius']);
    $matchingCategory = Category::factory()->create(['name' => 'Istorija']);
    $otherCategory = Category::factory()->create(['name' => 'Romanas']);
    $matchingPublisher = Publisher::factory()->create(['name' => 'Gera Leidykla']);
    $otherPublisher = Publisher::factory()->create(['name' => 'Kita Leidykla']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);

    $matchingBook = Book::factory()->create([
        'title' => 'Tinkama knyga',
        'publisher_id' => $matchingPublisher->id,
        'category_id' => $matchingCategory->id,
    ]);
    $matchingBook->authors()->sync([$matchingAuthor->id]);
    $matchingBook->categories()->sync([$matchingCategory->id]);

    $otherBook = Book::factory()->create([
        'title' => 'Netinkama knyga',
        'publisher_id' => $otherPublisher->id,
        'category_id' => $otherCategory->id,
    ]);
    $otherBook->authors()->sync([$otherAuthor->id]);
    $otherBook->categories()->sync([$otherCategory->id]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $matchingBook->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => 'available',
    ]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $otherBook->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => 'loaned',
    ]);

    $response = $this->actingAs($user)->get(route('books.index', [
        'author_id' => $matchingAuthor->id,
        'category_id' => $matchingCategory->id,
        'publisher_id' => $matchingPublisher->id,
        'availability' => 'available',
    ]));

    $response->assertOk();
    $response->assertSee('Tinkama knyga');
    $response->assertDontSee('Netinkama knyga');
});

it('filters book copies on the book page by status branch and location', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Filtruojama knyga']);

    $matchingBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Centrinis']);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Kitas']);
    $matchingLocation = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $matchingBranch->id, 'name' => 'Lentyna A']);
    $otherLocation = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $otherBranch->id, 'name' => 'Lentyna B']);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $matchingBranch->id,
        'location_id' => $matchingLocation->id,
        'inventory_code' => 'INV-MATCH-001',
        'status' => 'available',
    ]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $otherBranch->id,
        'location_id' => $otherLocation->id,
        'inventory_code' => 'INV-OTHER-001',
        'status' => 'loaned',
    ]);

    $response = $this->actingAs($user)->get(route('books.show', [
        'book' => $book,
        'copy_status' => 'available',
        'branch_id' => $matchingBranch->id,
        'location_id' => $matchingLocation->id,
    ]));

    $response->assertOk();
    $response->assertSee('INV-MATCH-001');
    $response->assertDontSee('INV-OTHER-001');
});

it('filters book copies on the book page by lifecycle group', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Gyvenimo ciklo filtrai']);
    $branch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Pagrindinis']);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id, 'name' => 'Lentyna C']);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-ISSUE-001',
        'status' => 'maintenance',
    ]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-REMOVED-001',
        'status' => 'withdrawn',
    ]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-ACTIVE-001',
        'status' => 'available',
    ]);

    $issuesResponse = $this->actingAs($user)->get(route('books.show', [
        'book' => $book,
        'copy_lifecycle' => 'issues',
    ]));

    $issuesResponse->assertOk();
    $issuesResponse->assertSee('INV-ISSUE-001');
    $issuesResponse->assertDontSee('INV-REMOVED-001');
    $issuesResponse->assertDontSee('INV-ACTIVE-001');

    $removedResponse = $this->actingAs($user)->get(route('books.show', [
        'book' => $book,
        'copy_lifecycle' => 'removed',
    ]));

    $removedResponse->assertOk();
    $removedResponse->assertSee('INV-REMOVED-001');
    $removedResponse->assertDontSee('INV-ISSUE-001');
    $removedResponse->assertDontSee('INV-ACTIVE-001');
});

it('filters loans by member employee and overdue status', function () {
    $library = Library::factory()->create();
    $user = User::factory()->staff()->create(['library_id' => $library->id]);
    $employee = User::factory()->staff()->create(['library_id' => $library->id, 'name' => 'Darbuotojas A']);
    $otherEmployee = User::factory()->staff()->create(['library_id' => $library->id, 'name' => 'Darbuotojas B']);
    $member = User::factory()->member()->create(['library_id' => $library->id, 'name' => 'Narys A']);
    $otherMember = User::factory()->member()->create(['library_id' => $library->id, 'name' => 'Narys B']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);

    $matchingCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'LOAN-MATCH-001',
    ]);

    $otherCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'LOAN-OTHER-001',
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $matchingCopy->id,
        'user_id' => $member->id,
        'issued_by' => $employee->id,
        'returned_at' => null,
        'status' => 'overdue',
        'borrowed_at' => now()->subDays(20),
        'due_at' => now()->subDays(5),
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $otherCopy->id,
        'user_id' => $otherMember->id,
        'issued_by' => $otherEmployee->id,
        'returned_at' => null,
        'status' => 'active',
        'borrowed_at' => now()->subDays(2),
        'due_at' => now()->addDays(10),
    ]);

    $response = $this->actingAs($user)->get(route('loans.index', [
        'member_id' => $member->id,
        'employee_id' => $employee->id,
        'overdue' => 'yes',
    ]));

    $response->assertOk();
    $response->assertSee('LOAN-MATCH-001');
    $response->assertDontSee('LOAN-OTHER-001');
});

it('filters reservations by queue and library', function () {
    $library = Library::factory()->create(['name' => 'Pirma biblioteka']);
    $otherLibrary = Library::factory()->create(['name' => 'Antra biblioteka']);
    $superAdmin = User::factory()->superAdmin()->create();
    $book = Book::factory()->create(['title' => 'Rezervuojama knyga']);
    $firstMember = User::factory()->member()->create(['library_id' => $library->id, 'name' => 'Pirmas narys']);
    $secondMember = User::factory()->member()->create(['library_id' => $library->id, 'name' => 'Antras narys']);
    $otherLibraryMember = User::factory()->member()->create(['library_id' => $otherLibrary->id, 'name' => 'Kitos bibliotekos narys']);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $firstMember->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHours(2),
        'expires_at' => now()->addDays(2),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $secondMember->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDays(2),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    Reservation::factory()->create([
        'library_id' => $otherLibrary->id,
        'book_id' => $book->id,
        'user_id' => $otherLibraryMember->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHours(3),
        'expires_at' => now()->addDays(2),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $response = $this->actingAs($superAdmin)->get(route('reservations.index', [
        'library_id' => $library->id,
        'queue' => 'first',
    ]));

    $response->assertOk();
    $response->assertSee('Pirmas narys');
    $response->assertDontSee('Antras narys');
    $response->assertDontSee('Kitos bibliotekos narys');
    $response->assertSee('#1');
});

it('shows global management search results for visible entities', function () {
    $library = Library::factory()->create(['name' => 'Paieskos biblioteka']);
    $superAdmin = User::factory()->superAdmin()->create();
    $managedUser = User::factory()->member()->create([
        'library_id' => $library->id,
        'name' => 'Paieskos vartotojas',
    ]);
    $author = Author::factory()->create(['name' => 'Paieskos autorius']);
    $branch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Paieskos filialas']);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id, 'name' => 'Paieskos vieta']);
    $category = Category::factory()->create(['name' => 'Paieskos kategorija']);
    $publisher = Publisher::factory()->create(['name' => 'Paieskos leidykla']);
    $book = Book::factory()->create([
        'title' => 'Paieskos knyga',
        'publisher_id' => $publisher->id,
        'category_id' => $category->id,
    ]);
    $book->authors()->sync([$author->id]);
    $book->categories()->sync([$category->id]);

    $response = $this->actingAs($superAdmin)->get(route('manage.search.index', [
        'q' => 'Paieskos',
    ]));

    $response->assertOk();
    $response->assertSee('Paieskos vartotojas');
    $response->assertSee('Paieskos autorius');
    $response->assertSee('Paieskos filialas');
    $response->assertSee('Paieskos vieta');
    $response->assertSee('Paieskos kategorija');
    $response->assertSee('Paieskos leidykla');
    $response->assertSee('Paieskos knyga');
});
