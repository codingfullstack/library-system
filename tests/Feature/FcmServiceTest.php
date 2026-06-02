<?php

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.firebase.project_id' => 'library-project']);
});

it('sends a firebase http v1 message to one token', function () {
    Http::fake([
        'https://fcm.googleapis.com/v1/projects/library-project/messages:send' => Http::response([
            'name' => 'projects/library-project/messages/message-id',
        ]),
    ]);

    $service = new FcmService(fn () => 'fake-access-token');

    $result = $service->sendToToken('token-1', 'Rezervacija paruosta', 'Knyga laukia atsiemimo.', [
        'type' => 'reservation_ready',
        'notification_id' => 'notification-uuid',
        'related_type' => 'reservation',
        'related_id' => 15,
        'deep_link' => 'libraryapp://notification/notification-uuid',
    ]);

    expect($result['sent'])->toBe(1)
        ->and($result['failed'])->toBe(0);

    Http::assertSent(function ($request) {
        $payload = $request->data();
        $message = $payload['message'];

        return $request->url() === 'https://fcm.googleapis.com/v1/projects/library-project/messages:send'
            && $request->hasHeader('Authorization', 'Bearer fake-access-token')
            && $message['token'] === 'token-1'
            && $message['notification']['title'] === 'Rezervacija paruosta'
            && $message['notification']['body'] === 'Knyga laukia atsiemimo.'
            && $message['data']['title'] === 'Rezervacija paruosta'
            && $message['data']['body'] === 'Knyga laukia atsiemimo.'
            && $message['data']['type'] === 'reservation_ready'
            && $message['data']['notification_id'] === 'notification-uuid'
            && $message['data']['related_type'] === 'reservation'
            && $message['data']['related_id'] === '15'
            && $message['data']['deep_link'] === 'libraryapp://notification/notification-uuid'
            && $message['android']['priority'] === 'high'
            && $message['android']['notification']['channel_id'] === 'library_notifications';
    });
});

it('sends to many unique non empty tokens', function () {
    Http::fake([
        'https://fcm.googleapis.com/v1/projects/library-project/messages:send' => Http::response([
            'name' => 'sent',
        ]),
    ]);

    $service = new FcmService(fn () => 'fake-access-token');

    $result = $service->sendToMany(['token-1', 'token-1', '', ' token-2 '], 'Title', 'Body');

    expect($result['sent'])->toBe(2)
        ->and($result['failed'])->toBe(0);

    Http::assertSentCount(2);
});

it('sends to all device tokens owned by a user', function () {
    Http::fake([
        'https://fcm.googleapis.com/v1/projects/library-project/messages:send' => Http::response([
            'name' => 'sent',
        ]),
    ]);

    $user = User::factory()->member()->create();
    $other = User::factory()->member()->create();

    DeviceToken::query()->create([
        'user_id' => $user->id,
        'token' => 'user-token-1',
    ]);

    DeviceToken::query()->create([
        'user_id' => $user->id,
        'token' => 'user-token-2',
    ]);

    DeviceToken::query()->create([
        'user_id' => $other->id,
        'token' => 'other-token',
    ]);

    $service = new FcmService(fn () => 'fake-access-token');

    $result = $service->sendToUser($user, 'Title', 'Body');

    expect($result['sent'])->toBe(2)
        ->and($result['failed'])->toBe(0);

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->data()['message']['token'] === 'user-token-1');
    Http::assertSent(fn ($request) => $request->data()['message']['token'] === 'user-token-2');
});

it('returns an empty result when no token is available', function () {
    Http::fake();

    $service = new FcmService(fn () => 'fake-access-token');

    expect($service->sendToToken('', 'Title', 'Body'))->toMatchArray([
        'sent' => 0,
        'failed' => 0,
        'responses' => [],
    ]);

    expect($service->sendToMany([], 'Title', 'Body'))->toMatchArray([
        'sent' => 0,
        'failed' => 0,
        'responses' => [],
    ]);

    Http::assertNothingSent();
});
