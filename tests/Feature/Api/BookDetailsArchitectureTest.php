<?php

use App\Http\Resources\BookCopyDetailsResource;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use App\Models\User;
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
        ->assertJsonPath('can_reserve', true)
        ->assertJsonPath('display_status', 'Galima');
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
