<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function memberInLibrary(App\Models\Library $library, array $attributes = []): App\Models\User
{
    return App\Models\User::factory()->member()->create(array_merge([
        'library_id' => $library->id,
        'is_active' => true,
    ], $attributes));
}

function adminInLibrary(App\Models\Library $library, array $attributes = []): App\Models\User
{
    return App\Models\User::factory()->admin()->create(array_merge([
        'library_id' => $library->id,
        'is_active' => true,
    ], $attributes));
}

function staffInBranch(App\Models\Library $library, App\Models\Branch $branch, array $attributes = []): App\Models\User
{
    if ((int) $branch->library_id !== (int) $library->id) {
        throw new InvalidArgumentException('Valid staff fixture branch must belong to the same library.');
    }

    $user = App\Models\User::factory()->staff()->create(array_merge([
        'library_id' => $library->id,
        'is_active' => true,
    ], $attributes));

    $user->libraryMemberships()
        ->where('library_id', $library->id)
        ->update([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

    return $user->refresh();
}

function superAdmin(array $attributes = []): App\Models\User
{
    return App\Models\User::factory()->superAdmin()->create(array_merge([
        'is_active' => true,
    ], $attributes));
}

function inactiveMemberInLibrary(App\Models\Library $library, array $attributes = []): App\Models\User
{
    $user = memberInLibrary($library, array_merge(['is_active' => true], $attributes));
    $user->libraryMemberships()->where('library_id', $library->id)->update(['is_active' => false]);

    return $user->refresh();
}

function userWithoutMembership(array $attributes = []): App\Models\User
{
    return App\Models\User::factory()->create(array_merge([
        'is_active' => true,
    ], $attributes));
}

function staffWithoutBranch(App\Models\Library $library, array $attributes = []): App\Models\User
{
    $user = App\Models\User::factory()->staff()->create(array_merge([
        'library_id' => $library->id,
        'is_active' => true,
    ], $attributes));

    $user->libraryMemberships()
        ->where('library_id', $library->id)
        ->update([
            'branch_id' => null,
            'is_active' => true,
        ]);

    return $user->refresh();
}

function staffWithForeignBranch(App\Models\Library $library, App\Models\Branch $foreignBranch, array $attributes = []): App\Models\User
{
    $user = staffWithoutBranch($library, $attributes);

    withTestForeignKeysDisabled(fn () => $user->libraryMemberships()
        ->where('library_id', $library->id)
        ->update(['branch_id' => $foreignBranch->id]));

    return $user->refresh();
}

function withTestForeignKeysDisabled(Closure $callback): mixed
{
    $driver = Illuminate\Support\Facades\DB::getDriverName();

    if ($driver === 'mysql') {
        Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
    } elseif ($driver === 'sqlite') {
        Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF');
    }

    try {
        return $callback();
    } finally {
        if ($driver === 'mysql') {
            Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($driver === 'sqlite') {
            Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON');
        }
    }
}





