<?php

use App\Models\DeviceToken;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\BookDueSoonNotification;
use App\Notifications\BookOverdueNotification;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\ReservationReadyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.firebase.project_id' => 'library-project']);
});

it('keeps database and broadcast channels while adding fcm', function (string $notificationClass) {
    $notification = new $notificationClass(
        title: 'Title',
        message: 'Body',
    );

    expect($notification->via(User::factory()->make()))->toBe([
        'database',
        'broadcast',
        FcmChannel::class,
    ]);
})->with([
    ReservationReadyNotification::class,
    BookDueSoonNotification::class,
    BookOverdueNotification::class,
]);

it('builds fcm payload with notification id and deep link', function () {
    Http::fake([
        'https://fcm.googleapis.com/v1/projects/library-project/messages:send' => Http::response([
            'name' => 'projects/library-project/messages/message-id',
        ]),
    ]);

    app()->bind(App\Services\FcmService::class, fn () => new App\Services\FcmService(fn () => 'fake-access-token'));

    $user = User::factory()->member()->create();
    DeviceToken::query()->create([
        'user_id' => $user->id,
        'token' => 'fcm-token',
    ]);

    $notification = new ReservationReadyNotification(
        title: 'Rezervacija paruosta',
        message: 'Knyga laukia atsiemimo.',
        relatedType: Loan::class,
        relatedId: 123,
    );

    Notification::sendNow($user, $notification, [FcmChannel::class]);

    Http::assertSent(function ($request) {
        $message = $request->data()['message'];
        $notificationId = $message['data']['notification_id'];

        return $message['token'] === 'fcm-token'
            && $message['notification']['title'] === 'Rezervacija paruosta'
            && $message['notification']['body'] === 'Knyga laukia atsiemimo.'
            && $message['data']['title'] === 'Rezervacija paruosta'
            && $message['data']['body'] === 'Knyga laukia atsiemimo.'
            && $message['data']['type'] === 'reservation_ready'
            && $notificationId !== ''
            && $message['data']['related_type'] === Loan::class
            && $message['data']['related_id'] === '123'
            && $message['data']['deep_link'] === "libraryapp://notification/{$notificationId}";
    });
});

it('stores database payload with the same deep link structure', function () {
    config(['queue.default' => 'sync']);
    Http::fake([
        'https://fcm.googleapis.com/v1/projects/library-project/messages:send' => Http::response([
            'name' => 'sent',
        ]),
    ]);

    app()->bind(App\Services\FcmService::class, fn () => new App\Services\FcmService(fn () => 'fake-access-token'));

    $user = User::factory()->member()->create();

    Notification::sendNow($user, new BookDueSoonNotification(
        title: 'Greitai grazinti',
        message: 'Terminas arteja.',
    ), ['database']);

    $stored = $user->notifications()->firstOrFail();

    expect($stored->data['notification_id'])->toBe($stored->id)
        ->and($stored->data['type'])->toBe('book_due_soon')
        ->and($stored->data['deep_link'])->toBe("libraryapp://notification/{$stored->id}")
        ->and($stored->data['body'])->toBe('Terminas arteja.');
});
