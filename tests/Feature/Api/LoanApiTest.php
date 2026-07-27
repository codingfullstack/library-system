<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createLoanForApi(Library $library, User $member, array $loanAttributes = [], array $bookAttributes = []): Loan
{
    $branch = Branch::query()->where('library_id', $library->id)->first()
        ?: Branch::factory()->create(['library_id' => $library->id]);
    $book = Book::factory()->create($bookAttributes);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
    ]);

    return Loan::factory()->create(array_merge([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'borrowed_at' => now()->subDay(),
        'due_at' => now()->addDays(3),
        'returned_at' => null,
    ], $loanAttributes));
}

it('returns canonical loan status fields from the api', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $loan = createLoanForApi($library, $member);

    $this->actingAs($staff)
        ->getJson('/api/auth/loans/active?status='.Loan::STATUS_ACTIVE)
        ->assertOk()
        ->assertJsonPath('data.0.id', $loan->id)
        ->assertJsonPath('data.0.status_label', 'Aktyvi')
        ->assertJsonPath('data.0.display_status', 'Aktyvi')
        ->assertJsonPath('data.0.is_overdue', false)
        ->assertJsonPath('data.0.is_due_soon', true)
        ->assertJsonPath('data.0.overdue_days', 0)
        ->assertJsonPath('data.0.can_return', true)
        ->assertJsonPath('data.0.can_renew', false)
        ->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('returns the first loans page with laravel paginator metadata and links', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);

    createLoanForApi($library, $member, ['due_at' => now()->addDay()]);
    createLoanForApi($library, $member, ['due_at' => now()->addDays(2)]);
    createLoanForApi($library, $member, ['due_at' => now()->addDays(3)]);

    $response = $this->actingAs($staff)
        ->getJson('/api/auth/loans/active?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('links.prev', null);

    expect($response->json('links.next'))->not->toBeNull();
});

it('returns subsequent loans pages', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);

    createLoanForApi($library, $member, ['due_at' => now()->addDay()]);
    createLoanForApi($library, $member, ['due_at' => now()->addDays(2)]);
    createLoanForApi($library, $member, ['due_at' => now()->addDays(3)]);

    $this->actingAs($staff)
        ->getJson('/api/auth/loans/active?per_page=2&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('links.next', null);
});

it('returns paginator metadata for an empty loans result', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->admin()->create(['library_id' => $library->id]);

    $this->actingAs($staff)
        ->getJson('/api/auth/loans/active?per_page=2')
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 1)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 0)
        ->assertJsonStructure([
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('keeps loan filters when paginating', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);

    $matching = createLoanForApi($library, $member, [
        'status' => Loan::STATUS_OVERDUE,
        'due_at' => now()->subDays(2),
    ], ['title' => 'Pagination Contract']);
    createLoanForApi($library, $member, [], ['title' => 'Other Book']);

    $response = $this->actingAs($staff)
        ->getJson('/api/auth/loans/active?search=Pagination&status='.urlencode(Loan::STATUS_OVERDUE).'&per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.per_page', 1);

    expect($response->json('links.first'))->not->toBeNull();
});

it('rejects an invalid loans page number', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->admin()->create(['library_id' => $library->id]);

    $this->actingAs($staff)
        ->getJson('/api/auth/loans/active?page=0')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('page');
});
