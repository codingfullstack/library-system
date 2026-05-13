<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows public home statistics from the database', function () {
    $library = Library::factory()->create(['is_active' => true]);
    Library::factory()->create(['is_active' => false]);
    $privateLibrary = Library::factory()->create(['is_active' => true, 'is_public' => false]);

    $book = Book::factory()->create();
    $privateBook = Book::factory()->create();
    Book::factory()->count(2)->create();

    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    BookCopy::factory()->count(4)->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
    ]);
    BookCopy::factory()->create([
        'library_id' => $privateLibrary->id,
        'book_id' => $privateBook->id,
    ]);

    User::factory()->member()->count(5)->create(['library_id' => $library->id]);
    User::factory()->staff()->create(['library_id' => $library->id]);

    $member = User::factory()->member()->create(['library_id' => $library->id]);
    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now(),
        'expires_at' => now()->addDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_CANCELLED,
        'reserved_at' => now(),
        'expires_at' => now()->addDay(),
        'cancelled_at' => now(),
    ]);

    $response = $this->get(route('home'));
    $memberCount = User::query()
        ->where('role', 'narys')
        ->whereHas('libraryMemberships', fn ($query) => $query
            ->where('library_id', $library->id)
            ->where('is_active', true))
        ->count();

    $response->assertOk();
    $response->assertViewHas('publicStats', [
        'books' => 1,
        'copies' => 4,
        'members' => $memberCount,
        'libraries' => Library::query()->where('is_active', true)->where('is_public', true)->count(),
        'activeReservations' => Reservation::query()->pending()->count(),
    ]);
    $response->assertSee(number_format(1));
    $response->assertSee(number_format($memberCount));
    $response->assertSee(number_format(Reservation::query()->pending()->count()));
});

it('passes database statistics to the about page', function () {
    $publicLibrary = Library::factory()->create(['is_active' => true, 'is_public' => true]);
    Library::factory()->create(['is_active' => true, 'is_public' => false]);
    BookCopy::factory()->count(3)->create(['library_id' => $publicLibrary->id]);

    $response = $this->get(route('about'));

    $response->assertOk();
    $response->assertViewHas('publicStats', fn (array $stats) => $stats['libraries'] === Library::query()->where('is_active', true)->where('is_public', true)->count()
        && $stats['copies'] === 3);
});

it('shows the account menu instead of login button for authenticated public pages', function () {
    $user = User::factory()->member()->create(['name' => 'Jonas Jonaitis']);

    $this->actingAs($user)
        ->get(route('public.libraries.index'))
        ->assertOk()
        ->assertSee($user->initials())
        ->assertSee('Apžvalga')
        ->assertSee('Nustatymai')
        ->assertSee('Atsijungti')
        ->assertDontSee('Prisijungti');
});





