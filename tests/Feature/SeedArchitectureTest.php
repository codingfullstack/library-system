<?php

use App\Models\Branch;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\Support\DemoAccessActorSynchronizer;
use Database\Seeders\Support\DemoDatasetMarker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function demoSeedCounts(): array
{
    return [
        'libraries' => Library::query()->count(),
        'branches' => Branch::query()->count(),
        'users' => User::query()->count(),
        'memberships' => LibraryMembership::query()->count(),
        'books' => Book::query()->count(),
        'copies' => BookCopy::query()->count(),
        'loans' => Loan::query()->count(),
        'reservations' => Reservation::query()->count(),
    ];
}

it('database seeder delegates to the single demo data chain', function () {
    $source = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

    expect($source)->toContain('DemoDataSeeder::class')
        ->and(substr_count($source, 'DemoDataSeeder::class'))->toBe(1);
});

it('physically removes legacy demo seeder files and config aliases', function () {
    $legacySeederFiles = [
        'Demo'.'Library'.'Seeder.php',
        'Kaltinenu'.'Library'.'Demo'.'Seeder.php',
        'Presentation'.'Demo'.'Data'.'Seeder.php',
    ];

    foreach ($legacySeederFiles as $file) {
        expect(database_path('seeders/'.$file))->not->toBeFile();
    }

    expect(config_path('demo'.'_libraries.php'))->not->toBeFile();
});

it('keeps active code free from legacy demo seeder references', function () {
    $needles = [
        'Demo'.'Library'.'Seeder',
        'Kaltinenu'.'Library'.'Demo'.'Seeder',
        'Presentation'.'Demo'.'Data'.'Seeder',
        'demo'.'_libraries',
    ];
    $roots = [
        app_path(),
        config_path(),
        database_path('seeders'),
        base_path('tests'),
    ];

    foreach ($roots as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $contents = file_get_contents($path);

            foreach ($needles as $needle) {
                expect($contents)->not->toContain($needle, $path.' contains '.$needle);
            }
        }
    }
});

it('uses config demo as the canonical demo data source', function () {
    expect(config('demo.libraries.LIB-X.staff'))->toBeArray()
        ->and(config('demo.password'))->toBe('password')
        ->and(config('demo.presentation.staff'))->toHaveCount(18);
});

it('blocks the demo data seeder when demo data is disabled', function () {
    config(['demo.enabled' => false]);

    expect(fn () => app(DemoDataSeeder::class)->run())
        ->toThrow(\RuntimeException::class, 'demo-only');
});

it('blocks demo seeders in production unless explicitly enabled', function () {
    $originalEnvironment = $this->app->environment();
    $this->app['env'] = 'production';
    config(['demo.enabled' => false]);

    try {
        expect(fn () => app(DemoDataSeeder::class)->run())
            ->toThrow(\RuntimeException::class, 'demo-only');
    } finally {
        $this->app['env'] = $originalEnvironment;
    }
});

it('syncs only declared demo actors for the matching library', function () {
    $library = Library::factory()->create(['code' => 'LIB-X']);
    $branches = collect(['LIB-X-BR-01', 'LIB-X-BR-02', 'LIB-X-BR-03', 'LIB-X-BR-04'])
        ->map(fn (string $code) => Branch::factory()->create(['library_id' => $library->id, 'code' => $code]));
    $realStaff = staffInBranch($library, $branches->first(), [
        'email' => 'real.staff@example.com',
        'name' => 'Real Staff',
    ]);
    $originalBranchId = $realStaff->membershipForLibrary($library->id)?->branch_id;

    (new DemoAccessActorSynchronizer())->syncLibrary($library);

    $realStaff->refresh()->load('libraryMemberships');
    $membership = $realStaff->membershipForLibrary($library->id);

    expect($realStaff->role)->toBe(User::ROLE_STAFF)
        ->and($realStaff->name)->toBe('Real Staff')
        ->and($membership?->is_active)->toBeTrue()
        ->and($membership?->branch_id)->toBe($originalBranchId)
        ->and(User::query()->where('email', 'adminx@test.com')->value('role'))->toBe(User::ROLE_ADMIN)
        ->and(User::query()->where('email', 'staffx@test.com')->value('role'))->toBe(User::ROLE_STAFF);
});

it('marks dataset completion idempotently by key library and version', function () {
    $library = Library::factory()->create();
    $marker = new DemoDatasetMarker();

    expect($marker->completed($library, 'presentation-demo-v2', '1'))->toBeFalse();

    $marker->markCompleted($library, 'presentation-demo-v2', '1', ['target_loans' => 10]);
    $marker->markCompleted($library, 'presentation-demo-v2', '1', ['target_loans' => 10]);

    expect($marker->completed($library, 'presentation-demo-v2', '1'))->toBeTrue()
        ->and($marker->completed($library, 'presentation-demo-v2', '2'))->toBeFalse()
        ->and(DB::table('demo_dataset_markers')->where('dataset_key', 'presentation-demo-v2')->where('library_id', $library->id)->count())->toBe(1);
});

it('does not keep a dataset marker when the surrounding seed transaction rolls back', function () {
    $library = Library::factory()->create();
    $marker = new DemoDatasetMarker();

    try {
        DB::transaction(function () use ($library, $marker): void {
            $marker->markCompleted($library, 'presentation-demo-v2', '1');

            throw new \RuntimeException('seed failed');
        });
    } catch (\RuntimeException) {
        // Expected rollback path.
    }

    expect($marker->completed($library, 'presentation-demo-v2', '1'))->toBeFalse()
        ->and(DB::table('demo_dataset_markers')->where('library_id', $library->id)->count())->toBe(0);
});

it('restores declared access actors even when presentation dataset marker already exists', function () {
    $library = Library::factory()->create(['code' => 'LIB-X']);
    collect(['LIB-X-BR-01', 'LIB-X-BR-02', 'LIB-X-BR-03', 'LIB-X-BR-04'])
        ->each(fn (string $code) => Branch::factory()->create(['library_id' => $library->id, 'code' => $code]));
    (new DemoAccessActorSynchronizer())->syncLibrary($library);
    (new DemoDatasetMarker())->markCompleted($library, 'presentation-demo-v2', '1');

    $admin = User::query()->where('email', 'adminx@test.com')->firstOrFail();
    $admin->forceFill(['role' => User::ROLE_MEMBER, 'is_active' => false])->save();
    LibraryMembership::query()
        ->where('user_id', $admin->id)
        ->where('library_id', $library->id)
        ->update(['is_active' => false]);

    (new DemoAccessActorSynchronizer())->syncLibrary($library);

    $admin->refresh()->load('libraryMemberships');

    expect($admin->role)->toBe(User::ROLE_ADMIN)
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->effectiveRole($library->id))->toBe(User::ROLE_ADMIN);
});

it('full demo seed leaves no active staff membership without a branch', function () {
    $this->seed(DatabaseSeeder::class);

    $branchlessStaffCount = LibraryMembership::query()
        ->join('users', 'users.id', '=', 'library_memberships.user_id')
        ->where('users.role', User::ROLE_STAFF)
        ->where('users.is_active', true)
        ->where('library_memberships.is_active', true)
        ->whereNull('library_memberships.branch_id')
        ->count();

    $foreignBranchCount = LibraryMembership::query()
        ->join('users', 'users.id', '=', 'library_memberships.user_id')
        ->join('branches', 'branches.id', '=', 'library_memberships.branch_id')
        ->where('users.role', User::ROLE_STAFF)
        ->where('users.is_active', true)
        ->where('library_memberships.is_active', true)
        ->whereColumn('branches.library_id', '<>', 'library_memberships.library_id')
        ->count();

    expect($branchlessStaffCount)->toBe(0)
        ->and($foreignBranchCount)->toBe(0);
});

it('full demo seed creates required manual testing accounts with expected contexts', function () {
    $this->seed(DatabaseSeeder::class);

    $expected = [
        'superadmin@test.com' => [User::ROLE_SUPER_ADMIN, null, null],
        'adminx@test.com' => [User::ROLE_ADMIN, 'LIB-X', null],
        'adminy@test.com' => [User::ROLE_ADMIN, 'LIB-Y', null],
        'staffx@test.com' => [User::ROLE_STAFF, 'LIB-X', 'LIB-X-BR-01'],
        'staffx.senamiestis@test.com' => [User::ROLE_STAFF, 'LIB-X', 'LIB-X-BR-02'],
        'membership.change@test.com' => [User::ROLE_STAFF, 'LIB-X', 'LIB-X-BR-01'],
        'egle.petrauskaite@example.com' => [User::ROLE_MEMBER, 'LIB-X', null],
        'ieva.noreikaite@example.com' => [User::ROLE_MEMBER, 'LIB-Y', null],
    ];

    foreach ($expected as $email => [$role, $libraryCode, $branchCode]) {
        $user = User::query()->where('email', $email)->firstOrFail();

        expect($user->role)->toBe($role)
            ->and($user->is_active)->toBeTrue();

        if ($libraryCode === null) {
            expect($user->libraryMemberships()->where('is_active', true)->exists())->toBeFalse();

            continue;
        }

        $library = Library::query()->where('code', $libraryCode)->firstOrFail();
        $membership = $user->membershipForLibrary($library->id);

        expect($membership?->is_active)->toBeTrue();

        if ($branchCode === null) {
            expect($membership?->branch_id)->toBeNull();
        } else {
            expect($membership?->branch?->code)->toBe($branchCode);
        }
    }
});

it('full demo seed keeps demo labels out of book titles and in copy identifiers', function () {
    $this->seed(DatabaseSeeder::class);

    $taggedBookTitleCount = Book::query()
        ->whereHas('bookCopies')
        ->get()
        ->filter(fn (Book $book) => preg_match('/^\[[^\]]+\] /', (string) $book->title) === 1)
        ->count();

    $badCopyCount = BookCopy::query()
        ->with(['library:id,code', 'branch:id,code'])
        ->get()
        ->reject(function (BookCopy $copy): bool {
            $libraryCode = (string) $copy->library?->code;
            $branchCode = (string) $copy->branch?->code;
            $prefix = Str::startsWith($branchCode, $libraryCode.'-')
                ? $branchCode.'-'
                : $libraryCode.'-'.$branchCode.'-';

            return Str::startsWith((string) $copy->inventory_code, $prefix);
        })
        ->count();

    expect($taggedBookTitleCount)->toBe(0)
        ->and($badCopyCount)->toBe(0)
        ->and(BookCopy::query()->where('inventory_code', 'like', 'LIB-X-X-%')->exists())->toBeFalse()
        ->and(BookCopy::query()->where('inventory_code', 'like', 'PRES-%')->exists())->toBeFalse()
        ->and(BookCopy::query()->where('inventory_code', 'like', 'KAL-%')->exists())->toBeFalse();
});

it('normalizes old tagged demo book titles without using title as the identity key', function () {
    $legacyBook = Book::factory()->create([
        'title' => '[LIB-X] Haris Poteris ir Išminties akmuo. 1 dalis',
        'isbn' => '9786090141601',
    ]);

    $this->seed(DatabaseSeeder::class);

    expect(Book::query()->where('isbn', '9786090141601')->count())->toBe(1)
        ->and($legacyBook->fresh()->title)->toBe('Haris Poteris ir Išminties akmuo. 1 dalis');
});

it('uses readable utf eight Lithuanian demo labels in active seed sources', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Library::query()->where('code', 'LIB-X')->value('name'))->toBe('Vilniaus miesto centrinė biblioteka')
        ->and(Library::query()->where('code', 'KALT-ASTU-001')->value('city'))->toBe('Kaltinėnai')
        ->and(Branch::query()->where('code', 'LIB-X-BR-03')->value('name'))->toBe('Žirmūnai')
        ->and(User::query()->where('email', 'staffx.senamiestis@test.com')->value('name'))->toBe('Rūta Senamiesčio')
        ->and(Book::query()->where('isbn', '9786090141601')->value('title'))->toBe('Haris Poteris ir Išminties akmuo. 1 dalis')
        ->and(Book::query()->where('isbn', '9786090141601')->value('title'))->not->toStartWith('[');
});

it('full demo seed is idempotent and restores the membership change actor', function () {
    $this->seed(DatabaseSeeder::class);

    $counts = demoSeedCounts();

    $user = User::query()->where('email', 'membership.change@test.com')->firstOrFail();
    $library = Library::query()->where('code', 'LIB-X')->firstOrFail();
    $branch = Branch::query()->where('library_id', $library->id)->where('code', 'LIB-X-BR-01')->firstOrFail();
    $user->forceFill(['role' => User::ROLE_MEMBER, 'is_active' => false])->save();
    $user->libraryMemberships()->where('library_id', $library->id)->update([
        'branch_id' => null,
        'is_active' => false,
    ]);

    $this->seed(DatabaseSeeder::class);

    expect(demoSeedCounts())->toEqual($counts);

    $user->refresh();
    $membership = $user->membershipForLibrary($library->id);

    expect($user->role)->toBe(User::ROLE_STAFF)
        ->and($user->is_active)->toBeTrue()
        ->and($membership?->is_active)->toBeTrue()
        ->and($membership?->branch_id)->toBe($branch->id);
});

it('full demo seed preserves loan reservation and membership invariants', function () {
    $this->seed(DatabaseSeeder::class);

    $duplicateActiveMemberships = LibraryMembership::query()
        ->select('user_id', 'library_id', DB::raw('count(*) as aggregate'))
        ->where('is_active', true)
        ->groupBy('user_id', 'library_id')
        ->having('aggregate', '>', 1)
        ->count();
    $duplicateActiveLoans = Loan::query()
        ->select('book_copy_id', DB::raw('count(*) as aggregate'))
        ->whereIn('status', [Loan::STATUS_ACTIVE, Loan::STATUS_OVERDUE])
        ->whereNull('returned_at')
        ->groupBy('book_copy_id')
        ->having('aggregate', '>', 1)
        ->count();
    $duplicateReadyReservations = Reservation::query()
        ->select('assigned_book_copy_id', DB::raw('count(*) as aggregate'))
        ->where('status', Reservation::STATUS_READY)
        ->whereNotNull('assigned_book_copy_id')
        ->groupBy('assigned_book_copy_id')
        ->having('aggregate', '>', 1)
        ->count();
    $incompleteReadyReservations = Reservation::query()
        ->where('status', Reservation::STATUS_READY)
        ->where(function ($query): void {
            $query->whereNull('assigned_book_copy_id')
                ->orWhereNull('pickup_branch_id')
                ->orWhereNull('ready_at')
                ->orWhereNull('expires_at');
        })
        ->count();

    expect($duplicateActiveMemberships)->toBe(0)
        ->and($duplicateActiveLoans)->toBe(0)
        ->and($duplicateReadyReservations)->toBe(0)
        ->and($incompleteReadyReservations)->toBe(0);
});

it('rejects a missing demo staff branch code with a clear error', function () {
    $library = Library::factory()->create(['code' => 'LIB-X']);

    expect(fn () => (new DemoAccessActorSynchronizer())->syncActor($library, [
        'email' => 'missing-branch@example.test',
        'name' => 'Missing Branch',
    ], User::ROLE_STAFF))->toThrow(
        \InvalidArgumentException::class,
        'missing-branch@example.test'
    )->toThrow(\InvalidArgumentException::class, 'branch_code');
});

it('rejects a demo staff branch code that does not exist in the target library', function () {
    $library = Library::factory()->create(['code' => 'LIB-X']);

    expect(fn () => (new DemoAccessActorSynchronizer())->syncActor($library, [
        'email' => 'unknown-branch@example.test',
        'name' => 'Unknown Branch',
        'branch_code' => 'DOES-NOT-EXIST',
    ], User::ROLE_STAFF))->toThrow(
        \InvalidArgumentException::class,
        'DOES-NOT-EXIST'
    );
});

it('rejects a demo staff branch code owned by another library', function () {
    $library = Library::factory()->create(['code' => 'LIB-X']);
    $otherLibrary = Library::factory()->create(['code' => 'LIB-Y']);
    Branch::factory()->create(['library_id' => $otherLibrary->id, 'code' => 'FOREIGN-BR']);

    expect(fn () => (new DemoAccessActorSynchronizer())->syncActor($library, [
        'email' => 'foreign-branch@example.test',
        'name' => 'Foreign Branch',
        'branch_code' => 'FOREIGN-BR',
    ], User::ROLE_STAFF))->toThrow(
        \InvalidArgumentException::class,
        'belongs to library "LIB-Y"'
    );
});

it('repeated demo actor sync does not duplicate users or memberships', function () {
    $library = Library::factory()->create(['code' => 'LIB-X']);
    collect(['LIB-X-BR-01', 'LIB-X-BR-02', 'LIB-X-BR-03', 'LIB-X-BR-04'])
        ->each(fn (string $code) => Branch::factory()->create(['library_id' => $library->id, 'code' => $code]));
    $sync = new DemoAccessActorSynchronizer();

    $sync->syncLibrary($library);
    $sync->syncLibrary($library);

    $emails = collect(config('demo.libraries.LIB-X.admins'))
        ->merge(config('demo.libraries.LIB-X.staff'))
        ->merge(config('demo.libraries.LIB-X.members'))
        ->pluck('email');

    expect(User::query()->whereIn('email', $emails)->count())->toBe($emails->count())
        ->and(LibraryMembership::query()
            ->where('library_id', $library->id)
            ->whereIn('user_id', User::query()->whereIn('email', $emails)->pluck('id'))
            ->count())->toBe($emails->count());
});

it('restores a stale null branch for an existing declared staff actor', function () {
    $library = Library::factory()->create(['code' => 'LIB-X']);
    $branch = Branch::factory()->create(['library_id' => $library->id, 'code' => 'LIB-X-BR-01']);
    collect(['LIB-X-BR-02', 'LIB-X-BR-03', 'LIB-X-BR-04'])
        ->each(fn (string $code) => Branch::factory()->create(['library_id' => $library->id, 'code' => $code]));

    (new DemoAccessActorSynchronizer())->syncLibrary($library);

    $staff = User::query()->where('email', 'staffx@test.com')->firstOrFail();
    LibraryMembership::query()
        ->where('library_id', $library->id)
        ->where('user_id', $staff->id)
        ->update(['branch_id' => null, 'is_active' => true]);

    (new DemoAccessActorSynchronizer())->syncLibrary($library);

    expect($staff->fresh()->membershipForLibrary($library->id)?->branch_id)->toBe($branch->id);
});

it('seeds a dedicated membership change staff account and restores its declared context', function () {
    $libraryX = Library::factory()->create(['code' => 'LIB-X']);
    $libraryY = Library::factory()->create(['code' => 'LIB-Y']);
    $branchX = Branch::factory()->create(['library_id' => $libraryX->id, 'code' => 'LIB-X-BR-01']);
    collect(['LIB-X-BR-02', 'LIB-X-BR-03', 'LIB-X-BR-04'])
        ->each(fn (string $code) => Branch::factory()->create(['library_id' => $libraryX->id, 'code' => $code]));
    $branchY = Branch::factory()->create(['library_id' => $libraryY->id, 'code' => 'LIB-Y-BR-01']);

    $sync = new DemoAccessActorSynchronizer();
    $sync->syncLibrary($libraryX);

    $user = User::query()->where('email', 'membership.change@test.com')->firstOrFail();
    $user->forceFill(['role' => User::ROLE_MEMBER, 'is_active' => false])->save();
    $user->libraryMemberships()->where('library_id', $libraryX->id)->update([
        'branch_id' => null,
        'is_active' => false,
    ]);
    $user->libraryMemberships()->create([
        'library_id' => $libraryY->id,
        'branch_id' => $branchY->id,
        'membership_number' => $user->membership_number,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    $sync->syncLibrary($libraryX);

    $user->refresh()->load('libraryMemberships');

    expect($user->role)->toBe(User::ROLE_STAFF)
        ->and($user->is_active)->toBeTrue()
        ->and($user->membershipForLibrary($libraryX->id)?->branch_id)->toBe($branchX->id)
        ->and($user->membershipForLibrary($libraryX->id)?->is_active)->toBeTrue()
        ->and($user->libraryMemberships()
            ->where('library_id', $libraryY->id)
            ->where('is_active', true)
            ->exists())->toBeFalse();
});

it('does not force admins or members to have a branch', function () {
    $library = Library::factory()->create(['code' => 'LIB-X']);
    collect(['LIB-X-BR-01', 'LIB-X-BR-02', 'LIB-X-BR-03', 'LIB-X-BR-04'])
        ->each(fn (string $code) => Branch::factory()->create(['library_id' => $library->id, 'code' => $code]));

    (new DemoAccessActorSynchronizer())->syncLibrary($library);

    $admin = User::query()->where('email', 'adminx@test.com')->firstOrFail();
    $member = User::query()->where('email', 'egle.petrauskaite@example.com')->firstOrFail();

    expect($admin->membershipForLibrary($library->id)?->branch_id)->toBeNull()
        ->and($member->membershipForLibrary($library->id)?->branch_id)->toBeNull();
});

it('isolated staff without a branch cannot manage branch resources', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffWithoutBranch($library);
    $member = memberInLibrary($library);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
    ]);
    $loan = Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);
    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branch->id,
        'status' => Reservation::STATUS_WAITING,
    ]);

    expect($staff->canManageBookCopy($copy))->toBeFalse()
        ->and($staff->canViewSensitiveLoanDetails($loan))->toBeFalse()
        ->and($staff->canCancelReservation($reservation))->toBeFalse();
});
