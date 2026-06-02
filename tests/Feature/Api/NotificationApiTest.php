<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createApiNotification(User $user, array $data = []): DatabaseNotification
{
    return $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => $data['kind'] ?? 'reservation_ready',
        'data' => array_merge([
            'kind' => 'reservation_ready',
            'type' => 'reservation_ready',
            'title' => 'Rezervacija paruosta',
            'body' => 'Knyga laukia atsiemimo.',
            'message' => 'Knyga laukia atsiemimo.',
            'notification_id' => '',
            'deep_link' => '',
            'related_type' => null,
            'related_id' => null,
            'metadata' => [],
            'created_at' => now()->toIso8601String(),
        ], $data),
    ]);
}

it('returns notifications and unread count for the authenticated user', function () {
    $user = User::factory()->member()->create();
    $other = User::factory()->member()->create();
    createApiNotification($user, ['title' => 'Mano pranesimas']);
    createApiNotification($other, ['title' => 'Kito pranesimas']);

    $this->actingAs($user)
        ->getJson('/api/auth/notifications')
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.title', 'Mano pranesimas');
});

it('returns unread notification count through the api', function () {
    $user = User::factory()->member()->create();
    createApiNotification($user);
    createApiNotification($user)->markAsRead();

    $this->actingAs($user)
        ->getJson('/api/auth/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('unread_count', 1);
});

it('marks one notification as read with the post alias', function () {
    $user = User::factory()->member()->create();
    $notification = createApiNotification($user);
    createApiNotification($user);

    $this->actingAs($user)
        ->postJson("/api/auth/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('message', 'Pranesimas pazymetas kaip perskaitytas.')
        ->assertJsonPath('unread_count', 1);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('keeps the legacy patch mark read endpoint working', function () {
    $user = User::factory()->member()->create();
    $notification = createApiNotification($user);

    $this->actingAs($user)
        ->patchJson("/api/auth/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('unread_count', 0);
});

it('does not allow marking another users notification as read', function () {
    $user = User::factory()->member()->create();
    $other = User::factory()->member()->create();
    $notification = createApiNotification($other);

    $this->actingAs($user)
        ->postJson("/api/auth/notifications/{$notification->id}/read")
        ->assertNotFound();
});

it('marks all authenticated user notifications as read with the post alias', function () {
    $user = User::factory()->member()->create();
    $other = User::factory()->member()->create();
    createApiNotification($user);
    createApiNotification($user);
    createApiNotification($other);

    $this->actingAs($user)
        ->postJson('/api/auth/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('message', 'Pranesimai pazymeti kaip perskaityti.')
        ->assertJsonPath('unread_count', 0);

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($other->unreadNotifications()->count())->toBe(1);
});

it('keeps the legacy mark all read endpoint working', function () {
    $user = User::factory()->member()->create();
    createApiNotification($user);

    $this->actingAs($user)
        ->postJson('/api/auth/notifications/mark-all-read')
        ->assertOk()
        ->assertJsonPath('unread_count', 0);
});
