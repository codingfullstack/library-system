<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;

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
