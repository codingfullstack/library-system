<?php

use Laravel\Fortify\Features;
use App\Models\User;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'phone' => '+37060000000',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticated();
    expect(User::where('email', 'test@example.com')->value('phone'))->toBe('+37060000000');
});





