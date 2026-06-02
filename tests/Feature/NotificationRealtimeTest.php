<?php

use App\Models\User;
use App\Notifications\ReservationReadyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createNativeNotification(User $user, array $data = []): \Illuminate\Notifications\DatabaseNotification
{
    return $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => $data['kind'] ?? 'reservation_ready',
        'data' => array_merge([
            'kind' => 'reservation_ready',
            'title' => 'Rezervacija paruosta',
            'message' => 'Knyga laukia atsiemimo.',
            'url' => route('notifications.index'),
            'created_at' => now()->toIso8601String(),
        ], $data),
    ]);
}

it('stores queued laravel notifications in the database', function () {
    config(['queue.default' => 'database']);

    $user = User::factory()->member()->create();

    $user->notify(new ReservationReadyNotification(
        title: 'Rezervacija paruosta',
        message: 'Knyga laukia atsiemimo.',
        url: route('notifications.index'),
    ));

    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'type' => 'reservation_ready',
    ]);

    expect(DB::table('jobs')->count())->toBe(1);
});

it('returns unread notification count for the authenticated user', function () {
    $user = User::factory()->member()->create();
    createNativeNotification($user);
    createNativeNotification($user, ['kind' => 'book_due_soon', 'title' => 'Greitai grazinti'])->markAsRead();

    $this->actingAs($user, 'web')
        ->getJson(route('notifications.unread-count'))
        ->assertOk()
        ->assertJson(['unread_count' => 1]);
});

it('does not allow reading another users notification', function () {
    $user = User::factory()->member()->create();
    $other = User::factory()->member()->create();
    $notification = createNativeNotification($other);

    $this->actingAs($user, 'web')
        ->patchJson(route('notifications.mark-read', $notification))
        ->assertNotFound();
});

it('marks only owned notifications as read', function () {
    $user = User::factory()->member()->create();
    $notification = createNativeNotification($user);

    $this->actingAs($user)
        ->patchJson(route('notifications.mark-read', $notification))
        ->assertOk()
        ->assertJson(['unread_count' => 0]);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('authorizes private user notification channels only for the owner', function () {
    config(['broadcasting.default' => 'reverb']);
    config(['broadcasting.connections.reverb.key' => 'testing-key']);
    config(['broadcasting.connections.reverb.secret' => 'testing-secret']);
    config(['broadcasting.connections.reverb.app_id' => 'testing-app']);
    app(\Illuminate\Contracts\Broadcasting\Factory::class)->forgetDrivers();
    require base_path('routes/channels.php');

    $user = User::factory()->member()->create();
    $other = User::factory()->member()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-users.'.$user->id,
        ])
        ->assertOk();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-users.'.$other->id,
        ])
        ->assertForbidden();
});
