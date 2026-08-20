<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;

function seedBookListFixture(): array
{
    $library = Library::factory()->create(['code' => 'BOOK-LIB']);
    $otherLibrary = Library::factory()->create(['code' => 'OTHER-LIB']);
    $branch = Branch::factory()->create(['library_id' => $library->id, 'code' => 'BOOK-LIB-BR-01']);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id, 'code' => 'BOOK-LIB-BR-02']);
    $foreignBranch = Branch::factory()->create(['library_id' => $otherLibrary->id, 'code' => 'OTHER-LIB-BR-01']);

    $branchBook = Book::factory()->create(['title' => 'Assigned Branch Book']);
    $otherBranchBook = Book::factory()->create(['title' => 'Other Branch Book']);
    $foreignBook = Book::factory()->create(['title' => 'Foreign Library Book']);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $branchBook->id,
        'branch_id' => $branch->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $otherBranchBook->id,
        'branch_id' => $otherBranch->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
    BookCopy::factory()->create([
        'library_id' => $otherLibrary->id,
        'book_id' => $foreignBook->id,
        'branch_id' => $foreignBranch->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    return compact('library', 'otherLibrary', 'branch', 'otherBranch', 'foreignBranch', 'branchBook', 'otherBranchBook', 'foreignBook');
}

it('accepts the Android book list query contract with integer branch scoping', function () {
    ['library' => $library] = seedBookListFixture();

    $this->actingAs(adminInLibrary($library))
        ->getJson('/api/auth/books?search=&availability=&sort=title&direction=asc&per_page=20&page=1&scope_to_assigned_branch=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('rejects the old Android textual boolean query value with a clear 422 response', function () {
    ['library' => $library] = seedBookListFixture();

    $this->actingAs(adminInLibrary($library))
        ->getJson('/api/auth/books?search=&availability=&sort=title&direction=asc&per_page=20&page=1&scope_to_assigned_branch=true')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The scope to assigned branch field must be true or false.')
        ->assertJsonValidationErrors(['scope_to_assigned_branch']);
});

it('scopes staff book lists to the assigned branch when Android requests branch scoping', function () {
    [
        'library' => $library,
        'branch' => $branch,
        'branchBook' => $branchBook,
        'otherBranchBook' => $otherBranchBook,
        'foreignBook' => $foreignBook,
    ] = seedBookListFixture();

    $response = $this->actingAs(staffInBranch($library, $branch))
        ->getJson('/api/auth/books?search=&availability=&sort=title&direction=asc&per_page=20&page=1&scope_to_assigned_branch=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $titles = collect($response->json('data'))->pluck('title');

    expect($titles)->toContain($branchBook->title)
        ->not->toContain($otherBranchBook->title)
        ->not->toContain($foreignBook->title);
});

it('does not force admins or members to have an assigned branch for book lists', function () {
    ['library' => $library] = seedBookListFixture();

    $this->actingAs(adminInLibrary($library))
        ->getJson('/api/auth/books?search=&availability=&sort=title&direction=asc&per_page=20&page=1&scope_to_assigned_branch=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $this->actingAs(memberInLibrary($library))
        ->getJson('/api/auth/books?search=&availability=&sort=title&direction=asc&per_page=20&page=1&scope_to_assigned_branch=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('allows active members to read every branch catalog item only in their active library', function () {
    [
        'library' => $library,
        'branchBook' => $branchBook,
        'otherBranchBook' => $otherBranchBook,
        'foreignBook' => $foreignBook,
    ] = seedBookListFixture();

    $response = $this->actingAs(memberInLibrary($library))
        ->getJson('/api/auth/books?search=&availability=&sort=title&direction=asc&per_page=20&page=1&scope_to_assigned_branch=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $titles = collect($response->json('data'))->pluck('title');

    expect($titles)->toContain($branchBook->title)
        ->toContain($otherBranchBook->title)
        ->not->toContain($foreignBook->title);
});

it('denies catalog access when the user or membership is inactive', function () {
    ['library' => $library] = seedBookListFixture();

    $this->actingAs(memberInLibrary($library, ['is_active' => false]))
        ->getJson('/api/auth/books?search=&availability=&sort=title&direction=asc&per_page=20&page=1')
        ->assertForbidden()
        ->assertJsonPath('code', 'account_inactive');

    $this->actingAs(inactiveMemberInLibrary($library))
        ->getJson('/api/auth/books?search=&availability=&sort=title&direction=asc&per_page=20&page=1')
        ->assertForbidden();
});

it('keeps member catalog access separate from operational book copy permissions', function () {
    [
        'library' => $library,
        'branchBook' => $branchBook,
    ] = seedBookListFixture();

    $member = memberInLibrary($library);
    $copy = BookCopy::query()
        ->where('library_id', $library->id)
        ->where('book_id', $branchBook->id)
        ->firstOrFail();

    $this->actingAs($member)
        ->getJson('/api/auth/books?search=&availability=&sort=title&direction=asc&per_page=20&page=1')
        ->assertOk();

    $this->actingAs($member)
        ->postJson('/api/auth/book-copies/'.$copy->id.'/borrow', [
            'user_id' => $member->id,
        ])
        ->assertForbidden();
});

it('documents the staff dashboard summary endpoint rejecting member access', function () {
    ['library' => $library] = seedBookListFixture();

    $this->actingAs(memberInLibrary($library))
        ->getJson('/api/auth/dashboard/summary')
        ->assertForbidden()
        ->assertJsonPath('message', '');
});

it('returns book list summary with total copies for the full filtered result set', function () {
    [$library, $branch] = seedBookSummaryLibrary();
    $book = Book::factory()->create(['title' => 'Galbut kazkada']);

    seedBookCopies($library, $branch, $book, 6, BookCopy::STATUS_MAINTENANCE);

    $this->actingAs(memberInLibrary($library))
        ->getJson('/api/auth/books?search=Galbut&sort=title&direction=asc&per_page=20&page=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.copies_count', 6)
        ->assertJsonPath('data.0.available_copies_count', 0)
        ->assertJsonPath('meta.summary.total_copies', 6)
        ->assertJsonPath('meta.summary.available_copies', 0);
});

it('counts only operationally available copies in book list summary', function () {
    [$library, $branch] = seedBookSummaryLibrary();
    $member = memberInLibrary($library);
    $book = Book::factory()->create(['title' => 'Miskas ir miestas']);

    seedBookCopies($library, $branch, $book, 3, BookCopy::STATUS_IN_CIRCULATION);
    $loanedCopies = seedBookCopies($library, $branch, $book, 2, BookCopy::STATUS_IN_CIRCULATION);
    seedBookCopies($library, $branch, $book, 1, BookCopy::STATUS_WITHDRAWN);

    foreach ($loanedCopies as $copy) {
        Loan::factory()->create([
            'library_id' => $library->id,
            'book_copy_id' => $copy->id,
            'user_id' => $member->id,
            'status' => Loan::STATUS_ACTIVE,
            'returned_at' => null,
        ]);
    }

    $this->actingAs($member)
        ->getJson('/api/auth/books?search=Miskas&sort=title&direction=asc&per_page=20&page=1')
        ->assertOk()
        ->assertJsonPath('data.0.copies_count', 6)
        ->assertJsonPath('data.0.available_copies_count', 3)
        ->assertJsonPath('meta.summary.total_copies', 6)
        ->assertJsonPath('meta.summary.available_copies', 3);
});

it('keeps book list summary independent from pagination pages', function () {
    [$library, $branch] = seedBookSummaryLibrary();
    $firstBook = Book::factory()->create(['title' => 'A pirmoji']);
    $secondBook = Book::factory()->create(['title' => 'B antroji']);

    seedBookCopies($library, $branch, $firstBook, 6, BookCopy::STATUS_IN_CIRCULATION);
    seedBookCopies($library, $branch, $secondBook, 4, BookCopy::STATUS_IN_CIRCULATION);

    $member = memberInLibrary($library);

    $this->actingAs($member)
        ->getJson('/api/auth/books?sort=title&direction=asc&per_page=1&page=1')
        ->assertOk()
        ->assertJsonPath('data.0.copies_count', 6)
        ->assertJsonPath('meta.summary.total_copies', 10)
        ->assertJsonPath('meta.summary.available_copies', 10);

    $this->actingAs($member)
        ->getJson('/api/auth/books?sort=title&direction=asc&per_page=1&page=2')
        ->assertOk()
        ->assertJsonPath('data.0.copies_count', 4)
        ->assertJsonPath('meta.summary.total_copies', 10)
        ->assertJsonPath('meta.summary.available_copies', 10);
});

it('recalculates book list summary for search and availability filters without leaking other libraries', function () {
    [$library, $branch] = seedBookSummaryLibrary();
    [$otherLibrary, $otherBranch] = seedBookSummaryLibrary('SUMMARY-OTHER');
    $matchingBook = Book::factory()->create(['title' => 'Filtruojama knyga']);
    $otherMatchingBook = Book::factory()->create(['title' => 'Filtruojama svetima']);
    $unavailableBook = Book::factory()->create(['title' => 'Filtruojama neprieinama']);

    seedBookCopies($library, $branch, $matchingBook, 2, BookCopy::STATUS_IN_CIRCULATION);
    seedBookCopies($library, $branch, $unavailableBook, 5, BookCopy::STATUS_MAINTENANCE);
    seedBookCopies($otherLibrary, $otherBranch, $otherMatchingBook, 9, BookCopy::STATUS_IN_CIRCULATION);

    $member = memberInLibrary($library);

    $this->actingAs($member)
        ->getJson('/api/auth/books?search=Filtruojama&sort=title&direction=asc&per_page=20&page=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.summary.total_copies', 7)
        ->assertJsonPath('meta.summary.available_copies', 2);

    $this->actingAs($member)
        ->getJson('/api/auth/books?search=Filtruojama&availability=laisva&sort=title&direction=asc&per_page=20&page=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.summary.total_copies', 2)
        ->assertJsonPath('meta.summary.available_copies', 2);
});

function seedBookSummaryLibrary(string $code = 'SUMMARY-LIB'): array
{
    $library = Library::factory()->create(['code' => $code]);
    $branch = Branch::factory()->create(['library_id' => $library->id, 'code' => $code.'-BR-01']);

    return [$library, $branch];
}

function seedBookCopies(Library $library, Branch $branch, Book $book, int $count, string $lifecycleStatus): \Illuminate\Support\Collection
{
    return BookCopy::factory()
        ->count($count)
        ->create([
            'library_id' => $library->id,
            'branch_id' => $branch->id,
            'book_id' => $book->id,
            'status' => BookCopy::STATUS_AVAILABLE,
            'lifecycle_status' => $lifecycleStatus,
        ]);
}
