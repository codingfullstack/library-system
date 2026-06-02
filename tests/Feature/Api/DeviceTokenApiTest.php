<?php

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores a device token for the authenticated user without duplicates', function () {
    $user = User::factory()->member()->create();

    $this->actingAs($user)
        ->postJson('/api/auth/device-token', [
            'token' => 'fcm-token-1',
            'device_name' => 'Pixel 8',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Ireginio token issaugotas.')
        ->assertJsonPath('device_token.device_name', 'Pixel 8');

    $this->actingAs($user)
        ->postJson('/api/auth/device-token', [
            'token' => 'fcm-token-1',
            'device_name' => 'Pixel 8 Pro',
        ])
        ->assertOk()
        ->assertJsonPath('device_token.device_name', 'Pixel 8 Pro');

    expect(DeviceToken::query()->where('token', 'fcm-token-1')->count())->toBe(1);

    $this->assertDatabaseHas('device_tokens', [
        'user_id' => $user->id,
        'token' => 'fcm-token-1',
        'device_name' => 'Pixel 8 Pro',
    ]);
});

it('moves an existing device token to the currently authenticated user', function () {
    $firstUser = User::factory()->member()->create();
    $secondUser = User::factory()->member()->create();

    DeviceToken::query()->create([
        'user_id' => $firstUser->id,
        'token' => 'shared-fcm-token',
        'device_name' => 'Old phone',
    ]);

    $this->actingAs($secondUser)
        ->postJson('/api/auth/device-token', [
            'token' => 'shared-fcm-token',
            'device_name' => 'New phone',
        ])
        ->assertOk();

    $this->assertDatabaseHas('device_tokens', [
        'user_id' => $secondUser->id,
        'token' => 'shared-fcm-token',
        'device_name' => 'New phone',
    ]);

    $this->assertDatabaseMissing('device_tokens', [
        'user_id' => $firstUser->id,
        'token' => 'shared-fcm-token',
    ]);
});

it('deletes only the authenticated users matching device token', function () {
    $user = User::factory()->member()->create();
    $other = User::factory()->member()->create();

    DeviceToken::query()->create([
        'user_id' => $user->id,
        'token' => 'own-token',
    ]);

    DeviceToken::query()->create([
        'user_id' => $other->id,
        'token' => 'other-token',
    ]);

    $this->actingAs($user)
        ->deleteJson('/api/auth/device-token', [
            'token' => 'own-token',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Ireginio token pasalintas.');

    $this->assertDatabaseMissing('device_tokens', [
        'user_id' => $user->id,
        'token' => 'own-token',
    ]);

    $this->assertDatabaseHas('device_tokens', [
        'user_id' => $other->id,
        'token' => 'other-token',
    ]);
});

it('requires a token when storing or deleting a device token', function () {
    $user = User::factory()->member()->create();

    $this->actingAs($user)
        ->postJson('/api/auth/device-token', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');

    $this->actingAs($user)
        ->deleteJson('/api/auth/device-token', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');
});
