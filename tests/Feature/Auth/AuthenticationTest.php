<?php

use App\Models\Library;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->superAdmin()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('member users are redirected to their account dashboard after login', function () {
    $library = Library::factory()->create();
    $user = memberInLibrary($library);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('account.dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('member with inactive selected library and active other library can authenticate', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $user = memberInLibrary($libraryA);

    $user->libraryMemberships()->create([
        'library_id' => $libraryB->id,
        'membership_number' => $user->membership_number,
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $user->libraryMemberships()->where('library_id', $libraryA->id)->update(['is_active' => false]);

    $response = $this
        ->withSession(['active_library_id' => $libraryA->id])
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('account.dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('member without active memberships gets localized membership message on login', function () {
    $library = Library::factory()->create();
    $user = memberInLibrary($library);

    $user->libraryMemberships()->where('library_id', $library->id)->update(['is_active' => false]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasErrors(['email' => 'Šiuo metu neturite aktyvios narystės bibliotekoje.'])
        ->assertSessionDoesntHaveErrors(['email' => 'Neteisingas el. pašto adresas arba slaptažodis.']);

    $this->assertGuest();
});

test('stale inactive library session context is replaced with an active membership', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $user = memberInLibrary($libraryA);

    $user->libraryMemberships()->create([
        'library_id' => $libraryB->id,
        'membership_number' => $user->membership_number,
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $user->libraryMemberships()->where('library_id', $libraryA->id)->update(['is_active' => false]);

    $this
        ->actingAs($user)
        ->withSession(['active_library_id' => $libraryA->id])
        ->get(route('account.dashboard'))
        ->assertOk()
        ->assertSessionHas('active_library_id', $libraryB->id);
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('invalid email and invalid password use the same localized login error', function () {
    $user = User::factory()->create();
    $message = 'Neteisingas el. pašto adresas arba slaptažodis.';

    $invalidPasswordResponse = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $unknownEmailResponse = $this->post(route('login.store'), [
        'email' => 'unknown@example.com',
        'password' => 'password',
    ]);

    $invalidPasswordResponse
        ->assertSessionHasErrors(['email' => $message])
        ->assertSessionDoesntHaveErrors(['email' => 'These credentials do not match our records.']);

    $unknownEmailResponse
        ->assertSessionHasErrors(['email' => $message])
        ->assertSessionDoesntHaveErrors(['email' => 'These credentials do not match our records.']);

    $this->assertGuest();
});

test('login validation errors are localized', function () {
    $response = $this->post(route('login.store'), [
        'email' => '',
        'password' => '',
    ]);

    $response->assertSessionHasErrors([
        'email' => 'Laukas el. pašto adresas yra privalomas.',
        'password' => 'Laukas slaptažodis yra privalomas.',
    ]);
});

test('login throttle error is localized', function () {
    $user = User::factory()->create();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response
        ->assertSessionHasErrors(['email' => 'Per daug bandymų prisijungti. Bandykite dar kartą po 60 sek.'])
        ->assertSessionDoesntHaveErrors(['email' => 'Too many login attempts.']);

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $library = Library::factory()->create();
    $user = User::factory()->withTwoFactor()->create([
        'library_id' => $library->id,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
