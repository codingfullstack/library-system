<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Publisher;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters books by author category publisher and availability', function () {
    $library = Library::factory()->create();
    $user = User::factory()->staff()->create(['library_id' => $library->id]);

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
        'status' => 'laisva',
    ]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $otherBook->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => 'išduota',
    ]);

    $response = $this->actingAs($user)->get(route('books.index', [
        'author_id' => $matchingAuthor->id,
        'category_id' => $matchingCategory->id,
        'publisher_id' => $matchingPublisher->id,
        'availability' => 'laisva',
    ]));

    $response->assertOk();
    $response->assertSee('Tinkama knyga');
    $response->assertDontSee('Netinkama knyga');
});

it('filters the staff book catalog by branch for administrators', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);

    $matchingBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Centrinis filialas']);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Vaiku filialas']);
    $matchingLocation = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $matchingBranch->id]);
    $otherLocation = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $otherBranch->id]);

    $matchingBook = Book::factory()->create(['title' => 'Knyga centriniame filiale']);
    $otherBook = Book::factory()->create(['title' => 'Knyga kitame filiale']);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $matchingBook->id,
        'branch_id' => $matchingBranch->id,
        'location_id' => $matchingLocation->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $otherBook->id,
        'branch_id' => $otherBranch->id,
        'location_id' => $otherLocation->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $response = $this->actingAs($admin)->get(route('books.index', [
        'branch_id' => $matchingBranch->id,
    ]));

    $response->assertOk();
    $response->assertSee('Centrinis filialas');
    $response->assertSee('Knyga centriniame filiale');
    $response->assertDontSee('Knyga kitame filiale');
});

it('filters book copies on the book page by status branch and location', function () {
    $library = Library::factory()->create();
    $user = User::factory()->staff()->create(['library_id' => $library->id]);
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
        'status' => 'laisva',
    ]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $otherBranch->id,
        'location_id' => $otherLocation->id,
        'inventory_code' => 'INV-OTHER-001',
        'status' => 'išduota',
    ]);

    $response = $this->actingAs($user)->get(route('books.show', [
        'book' => $book,
        'copy_status' => 'laisva',
        'branch_id' => $matchingBranch->id,
        'location_id' => $matchingLocation->id,
    ]));

    $response->assertOk();
    $response->assertSee('INV-MATCH-001');
    $response->assertDontSee('INV-OTHER-001');
});

it('searches book copies on the book page', function () {
    $library = Library::factory()->create();
    $user = User::factory()->staff()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Ieškoma knyga']);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'inventory_code' => 'LIB-X-X-026',
        'barcode' => 'BAR-026',
        'qr_code' => 'QR-026',
    ]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'inventory_code' => 'LIB-X-X-999',
        'barcode' => 'BAR-999',
        'qr_code' => 'QR-999',
    ]);

    $response = $this->actingAs($user)->get(route('books.show', [
        'book' => $book,
        'copy_search' => 'X-026',
    ]));

    $response->assertOk();
    $response->assertSee('LIB-X-X-026');
    $response->assertDontSee('LIB-X-X-999');
    $response->assertSee('value="X-026"', false);
});

it('uses only Lithuanian slug based book URLs', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Haris Poteris ir Išminties akmuo']);
    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
    ]);

    expect(rawurldecode(route('books.show', $book)))->toContain('/knygos/haris-poteris-ir-išminties-akmuo');

    $this->get('/knygos/'.$book->slug)
        ->assertRedirect(route('login'));

    $this->get('/books/'.$book->id)
        ->assertNotFound();

    $this->actingAs($user)
        ->get('/knygos/'.$book->slug)
        ->assertOk()
        ->assertSee('Haris Poteris');

    $this->actingAs($user)
        ->get('/books/'.$book->id)
        ->assertNotFound();
});

it('filters book copies on the book page by lifecycle group', function () {
    $library = Library::factory()->create();
    $user = User::factory()->staff()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Gyvenimo ciklo filtrai']);
    $branch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Pagrindinis']);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id, 'name' => 'Lentyna C']);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-ACTIVE-GOOD',
        'status' => BookCopy::STATUS_AVAILABLE,
        'condition_status' => BookCopy::CONDITION_GOOD,
    ]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-ACTIVE-DAMAGED',
        'status' => 'nurašyta',
    ]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-MAINTENANCE',
        'status' => BookCopy::STATUS_MAINTENANCE,
        'condition_status' => BookCopy::CONDITION_GOOD,
    ]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-LOANED-DAMAGED',
        'status' => BookCopy::STATUS_LOANED,
        'condition_status' => BookCopy::CONDITION_DAMAGED,
    ]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-LOST',
        'status' => BookCopy::STATUS_LOST,
        'condition_status' => BookCopy::CONDITION_DAMAGED,
    ]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-REMOVED-GOOD',
        'status' => BookCopy::STATUS_WITHDRAWN,
        'condition_status' => BookCopy::CONDITION_GOOD,
    ]);

    $activeResponse = $this->actingAs($user)->get(route('books.show', [
        'book' => $book,
        'copy_lifecycle' => 'aktyvi',
    ]));

    $activeResponse->assertOk();
    $activeResponse->assertSee('INV-ACTIVE-GOOD');
    $activeResponse->assertSee('INV-LOANED-DAMAGED');
    $activeResponse->assertDontSee('INV-MAINTENANCE');
    $activeResponse->assertDontSee('INV-LOST');
    $activeResponse->assertDontSee('INV-ACTIVE-DAMAGED');
    $activeResponse->assertDontSee('INV-REMOVED-GOOD');

    $issuesResponse = $this->actingAs($user)->get(route('books.show', [
        'book' => $book,
        'copy_lifecycle' => 'issues',
    ]));

    $issuesResponse->assertOk();
    $issuesResponse->assertSee('INV-LOANED-DAMAGED');
    $issuesResponse->assertSee('INV-MAINTENANCE');
    $issuesResponse->assertSee('INV-LOST');
    $issuesResponse->assertDontSee('INV-ACTIVE-GOOD');
    $issuesResponse->assertDontSee('INV-ACTIVE-DAMAGED');
    $issuesResponse->assertDontSee('INV-REMOVED-GOOD');

    $removedResponse = $this->actingAs($user)->get(route('books.show', [
        'book' => $book,
        'copy_lifecycle' => 'removed',
    ]));

    $removedResponse->assertOk();
    $removedResponse->assertSee('INV-ACTIVE-DAMAGED');
    $removedResponse->assertSee('INV-REMOVED-GOOD');
    $removedResponse->assertDontSee('INV-ACTIVE-GOOD');
    $removedResponse->assertDontSee('INV-LOANED-DAMAGED');
    $removedResponse->assertDontSee('INV-MAINTENANCE');
    $removedResponse->assertDontSee('INV-LOST');
});

it('filters loans by member employee and overdue status', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id, 'name' => 'Narys A']);
    $otherMember = User::factory()->member()->create(['library_id' => $library->id, 'name' => 'Narys B']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id]);
    $user = staffInBranch($library, $branch);
    $employee = staffInBranch($library, $branch, ['name' => 'Darbuotojas A']);
    $otherEmployee = staffInBranch($library, $otherBranch, ['name' => 'Darbuotojas B']);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $otherLocation = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $otherBranch->id]);

    $matchingCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'LOAN-MATCH-001',
    ]);

    $otherCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $otherBranch->id,
        'location_id' => $otherLocation->id,
        'inventory_code' => 'LOAN-OTHER-001',
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $matchingCopy->id,
        'user_id' => $member->id,
        'issued_by' => $employee->id,
        'returned_at' => null,
        'status' => 'vėluoja',
        'borrowed_at' => now()->subDays(20),
        'due_at' => now()->subDays(5),
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $otherCopy->id,
        'user_id' => $otherMember->id,
        'issued_by' => $otherEmployee->id,
        'returned_at' => null,
        'status' => 'aktyvi',
        'borrowed_at' => now()->subDays(2),
        'due_at' => now()->addDays(10),
    ]);

    $response = $this->actingAs($user)->get(route('loans.index', [
        'member_id' => $member->id,
        'employee_id' => $employee->id,
        'overdue' => 'yes',
        'branch_id' => $branch->id,
    ]));

    $response->assertOk();
    $response->assertSee('LOAN-MATCH-001');
    $response->assertDontSee('LOAN-OTHER-001');
});

it('filters library loans by branch for administrators', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $matchingBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Pagrindinis filialas']);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Kitas filialas']);
    $matchingLocation = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $matchingBranch->id]);
    $otherLocation = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $otherBranch->id]);

    $matchingCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $matchingBranch->id,
        'location_id' => $matchingLocation->id,
        'inventory_code' => 'BRANCH-LOAN-MATCH',
    ]);

    $otherCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $otherBranch->id,
        'location_id' => $otherLocation->id,
        'inventory_code' => 'BRANCH-LOAN-OTHER',
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $matchingCopy->id,
        'user_id' => $member->id,
        'returned_at' => null,
        'status' => 'aktyvi',
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $otherCopy->id,
        'user_id' => $member->id,
        'returned_at' => null,
        'status' => 'aktyvi',
    ]);

    $response = $this->actingAs($admin)->get(route('loans.index', [
        'branch_id' => $matchingBranch->id,
    ]));

    $response->assertOk();
    $response->assertSee('Pagrindinis filialas');
    $response->assertSee('BRANCH-LOAN-MATCH');
    $response->assertDontSee('BRANCH-LOAN-OTHER');
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
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHours(2),
        'expires_at' => now()->addDays(2),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $secondMember->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDays(2),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    Reservation::factory()->create([
        'library_id' => $otherLibrary->id,
        'book_id' => $book->id,
        'user_id' => $otherLibraryMember->id,
        'status' => Reservation::STATUS_WAITING,
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
    $response->assertSee('1');
});

it('filters library reservations by branch for administrators', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Filialo rezervacija']);
    $matchingMember = User::factory()->member()->create(['library_id' => $library->id, 'name' => 'Filialo narys']);
    $otherMember = User::factory()->member()->create(['library_id' => $library->id, 'name' => 'Kito filialo narys']);
    $matchingBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Rezervaciju filialas']);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Kitas rezervaciju filialas']);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $matchingMember->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $matchingBranch->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDays(2),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $otherMember->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $otherBranch->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(30),
        'expires_at' => now()->addDays(2),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $response = $this->actingAs($admin)->get(route('reservations.index', [
        'branch_id' => $matchingBranch->id,
    ]));

    $response->assertOk();
    $response->assertSee('Rezervaciju filialas');
    $response->assertSee('Filialo narys');
    $response->assertDontSee('Kito filialo narys');
});

it('filters explicitly expired reservations separately from waiting reservations', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $member = User::factory()->member()->create(['library_id' => $library->id, 'name' => 'Lukas Petrauskas']);
    $book = Book::factory()->create(['title' => 'Haris Poteris ir Išminties akmuo']);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_EXPIRED,
        'reserved_at' => now()->subDays(10),
        'ready_at' => now()->subDays(9),
        'expires_at' => now()->subDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $this->actingAs($staff)
        ->get(route('reservations.index', [
            'search' => 'haris',
            'status' => Reservation::STATUS_WAITING,
        ]))
        ->assertOk()
        ->assertDontSee('Haris Poteris ir Išminties akmuo');

    $this->actingAs($staff)
        ->get(route('reservations.index', ['search' => 'haris']))
        ->assertOk()
        ->assertSee('Haris Poteris ir Išminties akmuo')
        ->assertSee('Pasibaigusi')
        ->assertDontSee('>Aktyvi</span>', false);
});

it('shows global management search results for visible entities', function () {
    $library = Library::factory()->create(['name' => 'Paieškos biblioteka']);
    $superAdmin = User::factory()->superAdmin()->create();
    $managedUser = User::factory()->member()->create([
        'library_id' => $library->id,
        'name' => 'Paieškos vartotojas',
    ]);
    $author = Author::factory()->create(['name' => 'Paieškos autorius']);
    $branch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Paieškos filialas']);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id, 'name' => 'Paieškos vieta']);
    $category = Category::factory()->create(['name' => 'Paieškos kategorija']);
    $publisher = Publisher::factory()->create(['name' => 'Paieškos leidykla']);
    $book = Book::factory()->create([
        'title' => 'Paieškos knyga',
        'publisher_id' => $publisher->id,
        'category_id' => $category->id,
    ]);
    $book->authors()->sync([$author->id]);
    $book->categories()->sync([$category->id]);

    $response = $this->actingAs($superAdmin)->get(route('manage.search.index', [
        'q' => 'Paieškos',
    ]));

    $response->assertOk();
    $response->assertSee('Paieškos vartotojas');
    $response->assertSee('Paieškos autorius');
    $response->assertSee('Paieškos filialas');
    $response->assertSee('Paieškos vieta');
    $response->assertSee('Paieškos kategorija');
    $response->assertSee('Paieškos leidykla');
    $response->assertSee('Paieškos knyga');
});
