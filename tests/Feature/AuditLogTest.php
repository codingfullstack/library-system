<?php

use App\Actions\Loans\BorrowBookCopyAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows only superadmin to open the audit log page', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);

    $this->actingAs($superAdmin)
        ->get(route('manage.audit-logs.index'))
        ->assertOk()
        ->assertSee('Veiksmu istorija');

    $this->actingAs($admin)
        ->get(route('manage.audit-logs.index'))
        ->assertForbidden();
});

it('records an audit log when a book is updated', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $book = Book::factory()->create([
        'title' => 'Sena knyga',
        'isbn' => '1234567890',
    ]);

    $response = $this->actingAs($superAdmin)->put(route('manage.books.update', $book), [
        'title' => 'Atnaujinta knyga',
        'subtitle' => '',
        'isbn' => '1234567890',
        'description' => 'Naujas aprasymas',
        'publisher_id' => '',
        'publication_year' => '',
        'language' => 'lt',
        'page_count' => '',
        'edition' => '',
        'cover_image' => '',
        'author_ids' => [],
        'category_ids' => [],
    ]);

    $response->assertRedirect(route('manage.books.index'));

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'book_updated',
        'user_id' => $superAdmin->id,
        'auditable_type' => Book::class,
        'auditable_id' => $book->id,
    ]);
});

it('records an audit log when a book is issued and when copy status changes', function () {
    $library = Library::factory()->create(['name' => 'Testine biblioteka']);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create([
        'library_id' => $library->id,
        'name' => 'Jonas Skaitytojas',
        'membership_number' => 'TES-MEM-001',
    ]);
    $book = Book::factory()->create(['title' => 'Isskirtine knyga']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-AUDIT-001',
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    app(BorrowBookCopyAction::class)->handle($staff, $bookCopy, [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'notes' => 'Testinis isdavimas',
        'no_due_date' => false,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'loan_issued',
        'user_id' => $staff->id,
        'library_id' => $library->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'book_copy_status_changed',
        'user_id' => $staff->id,
        'library_id' => $library->id,
        'auditable_type' => BookCopy::class,
        'auditable_id' => $bookCopy->id,
    ]);
});

it('shows recent audit logs on the managed user page for superadmin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create(['name' => 'Centrine biblioteka']);
    $managedUser = User::factory()->member()->create([
        'library_id' => $library->id,
        'name' => 'Perziuros narys',
    ]);

    app(RecordAuditLogAction::class)->handle(
        $superAdmin,
        'user_updated',
        $managedUser,
        'Atnaujintas vartotojas "Perziuros narys".',
        ['changed_fields' => ['name']],
        $library->id
    );

    $this->actingAs($superAdmin)
        ->get(route('manage.users.show', $managedUser))
        ->assertOk()
        ->assertSee('Veiksmu istorija')
        ->assertSee('Atnaujintas vartotojas &quot;Perziuros narys&quot;.', false)
        ->assertSee('Pavadinimas')
        ->assertSee('Nauji atnaujinimai rodomi su pakeitimu is -> i.', false);
});

it('shows related reservation and issue audit logs on the book page for superadmin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create(['name' => 'Audit biblioteka']);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create([
        'library_id' => $library->id,
        'name' => 'Rezervacijos narys',
        'membership_number' => 'AUD-MEM-001',
    ]);
    $activeLoanMember = User::factory()->member()->create([
        'library_id' => $library->id,
        'name' => 'Esamas skaitytojas',
        'membership_number' => 'AUD-MEM-002',
    ]);
    $book = Book::factory()->create(['title' => 'Istorijos knyga']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-HISTORY-001',
        'status' => BookCopy::STATUS_LOANED,
    ]);

    $existingLoan = Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $bookCopy->id,
        'user_id' => $activeLoanMember->id,
        'issued_by' => $staff->id,
        'status' => 'active',
        'borrowed_at' => now()->subDays(5),
        'due_at' => now()->addDays(5),
        'returned_at' => null,
    ]);

    app(CreateReservationAction::class)->handle($staff, [
        'book_id' => $book->id,
        'user_id' => $member->id,
        'notes' => 'Testine rezervacija',
    ]);

    $existingLoan->update([
        'status' => 'returned',
        'returned_at' => now()->subMinute(),
        'received_by' => $staff->id,
    ]);

    $bookCopy->update(['status' => BookCopy::STATUS_AVAILABLE]);

    app(BorrowBookCopyAction::class)->handle($staff, $bookCopy, [
        'user_id' => $member->id,
        'due_at' => now()->addDays(7)->toDateString(),
        'notes' => 'Testinis isdavimas po rezervacijos',
        'no_due_date' => false,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('Veiksmu istorija')
        ->assertSee('Rezervacija sukurta')
        ->assertSee('Rezervacija ivykdyta')
        ->assertSee('Knyga isduota');
});

it('shows related audit logs on category and publisher edit pages for superadmin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $category = Category::factory()->create(['name' => 'Istorija']);
    $publisher = Publisher::factory()->create(['name' => 'Testo leidykla']);
    $book = Book::factory()->create([
        'title' => 'Susieta knyga',
        'category_id' => $category->id,
        'publisher_id' => $publisher->id,
    ]);
    $book->categories()->sync([$category->id]);

    app(RecordAuditLogAction::class)->handle(
        $superAdmin,
        'book_created',
        $book,
        'Sukurta knyga "Susieta knyga".',
        [
            'book_id' => $book->id,
            'book_title' => $book->title,
        ]
    );

    $this->actingAs($superAdmin)
        ->get(route('manage.categories.edit', $category))
        ->assertOk()
        ->assertSee('Veiksmu istorija')
        ->assertSee('Susieta knyga');

    $this->actingAs($superAdmin)
        ->get(route('manage.publishers.edit', $publisher))
        ->assertOk()
        ->assertSee('Veiksmu istorija')
        ->assertSee('Susieta knyga');
});

it('allows superadmin to open a book page even when it has no copies yet', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $book = Book::factory()->create([
        'title' => 'Katalogo knyga be egzemplioriu',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('Katalogo knyga be egzemplioriu')
        ->assertSee('Kopiju nerasta')
        ->assertSee('Prideti pirma egzemplioriu');
});

it('shows related audit logs on author edit page for superadmin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $author = \App\Models\Author::factory()->create(['name' => 'Audituojamas autorius']);
    $book = Book::factory()->create([
        'title' => 'Autoriaus knyga',
    ]);
    $book->authors()->sync([$author->id]);

    app(RecordAuditLogAction::class)->handle(
        $superAdmin,
        'book_created',
        $book,
        'Sukurta knyga "Autoriaus knyga".',
        [
            'book_id' => $book->id,
            'book_title' => $book->title,
        ]
    );

    $this->actingAs($superAdmin)
        ->get(route('manage.authors.edit', $author))
        ->assertOk()
        ->assertSee('Veiksmu istorija')
        ->assertSee('Autoriaus knyga');
});

it('shows related audit logs on book edit page for superadmin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $book = Book::factory()->create(['title' => 'Redaguojama knyga']);

    app(RecordAuditLogAction::class)->handle(
        $superAdmin,
        'book_updated',
        $book,
        'Atnaujinta knygos "Redaguojama knyga" informacija.',
        [
            'book_id' => $book->id,
            'book_title' => $book->title,
            'changed_fields' => ['title'],
            'changes' => [[
                'field' => 'title',
                'label' => 'Pavadinimas',
                'from' => 'Sena',
                'to' => 'Redaguojama knyga',
            ]],
        ]
    );

    $this->actingAs($superAdmin)
        ->get(route('manage.books.edit', $book))
        ->assertOk()
        ->assertSee('Veiksmu istorija')
        ->assertSee('Atnaujinta knygos &quot;Redaguojama knyga&quot; informacija.', false);
});

it('shows direct audit logs on book copy edit page for superadmin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $book = Book::factory()->create(['title' => 'Egzemplioriaus knyga']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-EDIT-001',
    ]);

    app(RecordAuditLogAction::class)->handle(
        $superAdmin,
        'book_copy_updated',
        $bookCopy,
        'Atnaujintas egzempliorius INV-EDIT-001.',
        [
            'inventory_code' => $bookCopy->inventory_code,
            'changed_fields' => ['notes'],
            'changes' => [[
                'field' => 'notes',
                'label' => 'Pastabos',
                'from' => '-',
                'to' => 'Pakeista',
            ]],
        ],
        $library->id
    );

    $this->actingAs($superAdmin)
        ->get(route('manage.book-copies.edit', $bookCopy))
        ->assertOk()
        ->assertSee('Veiksmu istorija')
        ->assertSee('Atnaujintas egzempliorius INV-EDIT-001.');
});

it('shows related audit logs on user edit page for superadmin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create(['name' => 'Vartotoju biblioteka']);
    $managedUser = User::factory()->member()->create([
        'library_id' => $library->id,
        'name' => 'Editinamas vartotojas',
    ]);

    app(RecordAuditLogAction::class)->handle(
        $superAdmin,
        'user_updated',
        $managedUser,
        'Atnaujintas vartotojas "Editinamas vartotojas".',
        [
            'changed_fields' => ['name'],
            'changes' => [[
                'field' => 'name',
                'label' => 'Vardas',
                'from' => 'Senas vardas',
                'to' => 'Editinamas vartotojas',
            ]],
        ],
        $library->id
    );

    $this->actingAs($superAdmin)
        ->get(route('manage.users.edit', $managedUser))
        ->assertOk()
        ->assertSee('Veiksmu istorija')
        ->assertSee('Atnaujintas vartotojas &quot;Editinamas vartotojas&quot;.', false);
});
