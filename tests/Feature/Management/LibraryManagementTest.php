<?php

use App\Models\Library;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets superadmin create a library with visibility settings', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->post(route('manage.libraries.store'), [
            'name' => 'Kauno miesto biblioteka',
            'code' => 'KMB',
            'email' => 'info@kmb.lt',
            'phone' => '+37060000000',
            'address' => 'Laisvės al. 1',
            'city' => 'Kaunas',
            'is_active' => '1',
            'is_public' => '1',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('libraries', [
        'name' => 'Kauno miesto biblioteka',
        'code' => 'KMB',
        'city' => 'Kaunas',
        'is_active' => true,
        'is_public' => true,
    ]);
});

it('does not allow regular library roles to manage libraries', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);

    $this->actingAs($admin)
        ->withSession(['active_library_id' => $library->id])
        ->get(route('manage.libraries.index'))
        ->assertForbidden();
});

it('lets superadmin assign admin and staff users to a library', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => null]);

    $this->actingAs($superAdmin)
        ->post(route('manage.libraries.staff.store', $library), [
            'email' => $user->email,
            'role' => 'darbuotojas',
        ])
        ->assertRedirect();

    expect($user->fresh()->role)->toBe('darbuotojas')
        ->and($user->fresh()->library_id)->toBe($library->id)
        ->and($user->fresh()->is_active)->toBeTrue();

    $this->actingAs($superAdmin)
        ->post(route('manage.libraries.staff.store', $library), [
            'email' => $user->email,
            'role' => 'administratorius',
        ])
        ->assertRedirect();

    expect($user->fresh()->role)->toBe('administratorius')
        ->and($user->fresh()->library_id)->toBe($library->id);
});

it('lets superadmin deactivate and remove a staff assignment', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create([
        'library_id' => $library->id,
        'membership_number' => null,
    ]);

    $this->actingAs($superAdmin)
        ->patch(route('manage.libraries.staff.toggle', [$library, $staff]))
        ->assertRedirect();

    expect($staff->fresh()->libraryMemberships()->where('library_id', $library->id)->value('is_active'))->toBeFalse();

    $this->actingAs($superAdmin)
        ->delete(route('manage.libraries.staff.destroy', [$library, $staff]))
        ->assertRedirect();

    expect($staff->fresh()->role)->toBe('narys')
        ->and($staff->fresh()->library_id)->toBeNull()
        ->and($staff->fresh()->is_active)->toBeTrue();
});





