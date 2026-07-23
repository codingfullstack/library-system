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
    $copies = BookCopy::factory()->count(4)->create([
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
        'status' => Reservation::STATUS_WAITING,
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
    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'pickup_branch_id' => $branch->id,
        'assigned_book_copy_id' => $copies[0]->id,
        'status' => Reservation::STATUS_READY,
        'reserved_at' => now(),
        'ready_at' => now(),
        'expires_at' => now()->addDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
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
        'activeReservations' => Reservation::query()->active()->count(),
    ]);
    $response->assertSee(number_format(1));
    $response->assertSee(number_format($memberCount));
    $response->assertSee(number_format(Reservation::query()->active()->count()));
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

it('renders unique seo metadata for public pages', function (string $routeName, string $title, string $description) {
    $canonicalUrl = route($routeName);
    $fullTitle = $title.' | '.config('seo.site_name');
    $response = $this->get($canonicalUrl);
    $html = $response->getContent();

    $response->assertOk()
        ->assertSee('<title>'.$fullTitle.'</title>', false)
        ->assertSee('<meta name="description" content="'.$description.'">', false)
        ->assertSee('<link rel="canonical" href="'.$canonicalUrl.'">', false)
        ->assertSee('<meta property="og:title" content="'.$fullTitle.'">', false)
        ->assertSee('<meta property="og:description" content="'.$description.'">', false)
        ->assertSee('<meta property="og:url" content="'.$canonicalUrl.'">', false)
        ->assertSee('<meta property="og:site_name" content="'.config('seo.site_name').'">', false)
        ->assertSee('<meta property="og:locale" content="lt_LT">', false)
        ->assertSee('<meta property="og:type" content="website">', false)
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee('<meta name="twitter:title" content="'.$fullTitle.'">', false)
        ->assertSee('<meta name="twitter:description" content="'.$description.'">', false)
        ->assertSee('<script type="application/ld+json">', false);

    expect(substr_count($html, '<title>'))->toBe(1)
        ->and(substr_count($html, 'name="description"'))->toBe(1)
        ->and(substr_count($html, 'rel="canonical"'))->toBe(1)
        ->and(substr_count($html, 'property="og:title"'))->toBe(1)
        ->and(substr_count($html, 'name="twitter:title"'))->toBe(1);
})->with([
    'home' => [
        'home',
        'Bibliotekų valdymo sistema',
        'Moderni bibliotekų valdymo sistema bibliotekoms, darbuotojams ir skaitytojams.',
    ],
    'libraries' => [
        'public.libraries.index',
        'Viešų bibliotekų sąrašas',
        'Peržiūrėkite viešų bibliotekų sąrašą, raskite biblioteką ir sužinokite pagrindinę jos informaciją.',
    ],
    'about' => [
        'about',
        'Apie bibliotekų sistemą',
        'Sužinokite apie bibliotekų valdymo sistemą, jos galimybes ir naudą bibliotekoms bei skaitytojams.',
    ],
    'contacts' => [
        'contacts',
        'Kontaktai ir susisiekimas',
        'Susisiekite dėl sistemos naudojimo, pagalbos ar papildomos informacijos.',
    ],
    'help' => [
        'help',
        'Pagalba vartotojams',
        'Raskite atsakymus į dažniausiai užduodamus klausimus ir naudojimosi sistema instrukcijas.',
    ],
]);

it('marks authenticated catalog pages as noindex', function () {
    $user = User::factory()->member()->create();

    $this->actingAs($user)
        ->get(route('books.index'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
});

it('uses only Lithuanian public page paths', function () {
    $library = Library::factory()->create([
        'name' => 'Kaltinėnų A. Stulginskio biblioteka',
        'is_active' => true,
        'is_public' => true,
    ]);

    expect(route('about'))->toContain('/apie');
    expect(route('contacts'))->toContain('/kontaktai');
    expect(route('help'))->toContain('/pagalba');
    expect(route('public.libraries.index'))->toContain('/bibliotekos');
    expect(rawurldecode(route('public.libraries.show', ['library' => $library->slug])))
        ->toContain('/bibliotekos/kaltinėnų-a-stulginskio-biblioteka');

    $this->get('/about')->assertNotFound();
    $this->get('/contact')->assertNotFound();
    $this->get('/libraries')->assertNotFound();
    $this->get(route('help'))
        ->assertOk()
        ->assertSee('Greita pagalba');
    $this->get(route('public.libraries.show', ['library' => $library->slug]))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->member()->create())
        ->get('/libraries/'.$library->id)
        ->assertNotFound();
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

it('requires authentication for the books catalog', function () {
    $library = Library::factory()->create(['is_active' => true, 'is_public' => true]);
    $book = Book::factory()->create(['title' => 'Vieša katalogo knyga']);
    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee(route('books.index'), false);

    $this->get(route('books.index'))
        ->assertRedirect(route('login'));
});
