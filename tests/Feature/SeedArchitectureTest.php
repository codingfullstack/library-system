<?php

use App\Models\Branch;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\User;
use Database\Seeders\DemoLibrarySeeder;
use Database\Seeders\KaltinenuLibraryDemoSeeder;
use Database\Seeders\PresentationDemoDataSeeder;
use Database\Seeders\Support\DemoAccessActorSynchronizer;
use Database\Seeders\Support\DemoDatasetMarker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('blocks demo seeders in production', function (string $seederClass) {
    $originalEnvironment = $this->app->environment();
    $this->app['env'] = 'production';

    try {
        expect(fn () => app($seederClass)->run())
            ->toThrow(\RuntimeException::class, 'demo-only');
    } finally {
        $this->app['env'] = $originalEnvironment;
    }
})->with([
    DemoLibrarySeeder::class,
    KaltinenuLibraryDemoSeeder::class,
    PresentationDemoDataSeeder::class,
]);

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
