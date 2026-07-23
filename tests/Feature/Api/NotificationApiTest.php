<?php

use App\Models\User;
use App\Support\Notifications\NotificationType;
use App\Support\Notifications\NotificationUiConfig;
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
    createApiNotification($user, ['title' => 'Mano pranešimas']);
    createApiNotification($other, ['title' => 'Kito pranešimas']);

    $this->actingAs($user)
        ->getJson('/api/auth/notifications')
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'reservation_ready')
        ->assertJsonPath('data.0.kind', 'reservation_ready')
        ->assertJsonPath('data.0.title', 'Mano pranešimas')
        ->assertJsonPath('data.0.ui.type', 'RESERVATION')
        ->assertJsonPath('data.0.category', 'Rezervacija')
        ->assertJsonPath('data.0.icon', 'bookmark')
        ->assertJsonPath('data.0.color', 'Indigo')
        ->assertJsonPath('data.0.badge', 'reservation')
        ->assertJsonPath('data.0.priority', 'medium')
        ->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('uses NotificationType as the event type registry', function () {
    expect(array_map(fn (NotificationType $type) => $type->value, NotificationType::cases()))
        ->toBe(array_keys(NotificationUiConfig::all()));
});

it('defines complete UI metadata for every notification type', function () {
    $allowedCategoryKeys = ['info', 'success', 'warning', 'error', 'book', 'reservation', 'loan'];
    $forbiddenCategories = ['Sėkmė', 'Success', 'Kita', 'Bendras', 'Misc', 'Unknown'];

    foreach (NotificationUiConfig::all() as $type => $config) {
        expect($config['type'])->not->toBeEmpty()
            ->and($config['label'])->not->toBeEmpty()
            ->and($config['category'])->not->toBeEmpty()
            ->and($config['category_key'])->not->toBeEmpty()
            ->and($config['icon'])->not->toBeEmpty()
            ->and($config['color'])->not->toBeEmpty()
            ->and($config['title'])->not->toBeEmpty()
            ->and($config['subtitle'])->not->toBeEmpty()
            ->and($config['badge'])->not->toBeEmpty()
            ->and($config['priority'])->toBeIn(['normal', 'medium', 'high'])
            ->and($config['web']['badge'])->not->toBeEmpty()
            ->and($config['web']['icon_wrap'])->not->toBeEmpty()
            ->and($config['web']['icon'])->not->toBeEmpty()
            ->and($config['web']['icon_svg'])->not->toBeEmpty()
            ->and($config['category_key'])->toBeIn($allowedCategoryKeys)
            ->and($config['category'])->not->toBeIn($forbiddenCategories);

        expect(NotificationUiConfig::for($type))->toBe($config);
    }
});

it('maps the required notification types to the shared UI specification', function (string $type, string $category, string $color, string $icon) {
    $ui = NotificationUiConfig::for($type);

    expect($ui['category'])->toBe($category)
        ->and($ui['color'])->toBe($color)
        ->and($ui['icon'])->toBe($icon);
})->with([
    'reservation created' => ['reservation_created', 'Rezervacija', 'Indigo', 'bookmark'],
    'reservation position changed' => ['reservation_queue_changed', 'Rezervacija', 'Indigo', 'bookmark'],
    'reservation ready' => ['reservation_ready', 'Rezervacija', 'Indigo', 'bookmark'],
    'reservation expired' => ['reservation_expired', 'Rezervacija', 'Indigo', 'bookmark'],
    'reservation fulfilled' => ['reservation_fulfilled', 'Rezervacija', 'Indigo', 'bookmark'],
    'book returned' => ['book_returned', 'Paskola', 'Teal', 'schedule'],
    'loan due soon' => ['book_due_soon', 'Paskola', 'Teal', 'schedule'],
    'loan overdue' => ['loan_overdue', 'Paskola', 'Teal', 'schedule'],
    'system info' => ['system', 'Informacinis', 'Blue', 'info'],
    'system warning' => ['system_warning', 'Perspėjimas', 'Orange', 'warning'],
    'system error' => ['system_error', 'Klaida', 'Red', 'error'],
    'security alert' => ['account_security', 'Perspėjimas', 'Orange', 'warning'],
]);

it('returns current UI metadata instead of stale stored notification data', function () {
    $user = User::factory()->member()->create();

    createApiNotification($user, [
        'kind' => 'reservation_fulfilled',
        'type' => 'reservation_fulfilled',
        'ui' => [
            'type' => 'RESERVATION_FULFILLED',
            'category_key' => 'success',
            'category' => 'Sėkmė',
            'icon' => 'success',
            'color' => 'green',
        ],
    ]);
    createApiNotification($user, [
        'kind' => 'book_returned',
        'type' => 'book_returned',
        'ui' => [
            'type' => 'BOOK_RETURNED',
            'category_key' => 'success',
            'category' => 'Sėkmė',
            'icon' => 'success',
            'color' => 'green',
        ],
    ]);

    $items = $this->actingAs($user)
        ->getJson('/api/auth/notifications')
        ->assertOk()
        ->json('data');

    $itemsByKind = collect($items)->keyBy('kind');

    expect($itemsByKind['book_returned']['ui']['category'])->toBe('Paskola')
        ->and($itemsByKind['book_returned']['ui']['color'])->toBe('Teal')
        ->and($itemsByKind['book_returned']['ui']['icon'])->toBe('schedule')
        ->and($itemsByKind['reservation_fulfilled']['ui']['category'])->toBe('Rezervacija')
        ->and($itemsByKind['reservation_fulfilled']['ui']['color'])->toBe('Indigo')
        ->and($itemsByKind['reservation_fulfilled']['ui']['icon'])->toBe('bookmark');
});

it('returns branch notification metadata for mobile clients without parsing text', function () {
    $user = User::factory()->member()->create();

    createApiNotification($user, [
        'kind' => 'reservation_ready',
        'type' => 'reservation_ready',
        'title' => 'Rezervacija paruošta',
        'message' => 'Knyga laukia atsiėmimo.',
        'metadata' => [
            'reservation_id' => 15,
            'book_title' => 'Testinė knyga',
            'pickup_branch_id' => 5,
            'pickup_branch_name' => 'Centrinis filialas',
            'expires_at' => '2026-07-20 12:00:00',
        ],
    ]);

    $this->actingAs($user)
        ->getJson('/api/auth/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.metadata.pickup_branch_id', 5)
        ->assertJsonPath('data.0.metadata.pickup_branch_name', 'Centrinis filialas')
        ->assertJsonPath('data.0.metadata.book_title', 'Testinė knyga');
});

it('uses the standardized notification metadata keys', function () {
    $user = User::factory()->member()->create();

    createApiNotification($user, [
        'kind' => NotificationType::RESERVATION_QUEUE_CHANGED->value,
        'type' => NotificationType::RESERVATION_QUEUE_CHANGED->value,
        'metadata' => [
            'reservation_id' => 10,
            'book_id' => 20,
            'book_title' => 'Queue book',
            'queue_position' => 2,
            'old_queue_position' => 3,
            'new_queue_position' => 2,
        ],
    ]);

    $metadata = $this->actingAs($user)
        ->getJson('/api/auth/notifications')
        ->assertOk()
        ->json('data.0.metadata');

    expect($metadata)->toHaveKeys(['old_queue_position', 'new_queue_position'])
        ->and($metadata)->not->toHaveKeys(['old_position', 'new_position', 'pickup_expires_at', 'book_copy_id', 'inventory_code']);
});

it('returns a safe fallback for unknown notification types without using success', function () {
    $user = User::factory()->member()->create();

    createApiNotification($user, [
        'kind' => 'unknown_notification_type',
        'type' => 'unknown_notification_type',
        'title' => 'Nežinomas pranešimas',
    ]);

    $this->actingAs($user)
        ->getJson('/api/auth/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.ui.category', 'Informacinis')
        ->assertJsonPath('data.0.ui.color', 'Blue')
        ->assertJsonPath('data.0.ui.icon', 'info');
});

it('keeps web notification screen free from per-type UI mappers', function () {
    $webIndex = file_get_contents(resource_path('views/notifications/index.blade.php'));

    expect($webIndex)->not->toContain('categoryMeta')
        ->and($webIndex)->not->toContain('match ($type)')
        ->and($webIndex)->not->toContain('Sėkmė')
        ->and($webIndex)->not->toContain('Informacija');
});

it('filters by configured category keys and returns no rows for empty categories', function () {
    $user = User::factory()->member()->create();

    createApiNotification($user, ['kind' => 'book_returned', 'type' => 'book_returned', 'title' => 'Knyga grąžinta']);
    createApiNotification($user, ['kind' => 'reservation_created', 'type' => 'reservation_created', 'title' => 'Rezervacija sukurta']);

    $this->actingAs($user)
        ->get('/notifications?category=loan')
        ->assertOk()
        ->assertSee('Knyga grąžinta')
        ->assertDontSee('Rezervacija sukurta');

    $this->actingAs($user)
        ->get('/notifications?category=book')
        ->assertOk()
        ->assertSee('Pranešimų nėra');
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
        ->assertJsonPath('message', 'Pranešimas pažymėtas kaip perskaitytas.')
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
        ->assertJsonPath('message', 'Pranešimai pažymėti kaip perskaityti.')
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
