<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Notifications\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

uses(RefreshDatabase::class);

function apiContractSpec(): array
{
    return Yaml::parseFile(base_path('docs/api/openapi.yaml'));
}

function apiContractSchema(string $name): array
{
    $schema = apiContractSpec()['components']['schemas'][$name] ?? null;

    expect($schema)->not->toBeNull("Missing OpenAPI schema {$name}.");

    return apiContractResolveSchema($schema);
}

function apiContractResolveSchema(array $schema): array
{
    if (isset($schema['$ref'])) {
        $name = basename(str_replace('\\', '/', $schema['$ref']));

        return apiContractSchema($name);
    }

    if (isset($schema['allOf'])) {
        $merged = ['type' => 'object', 'required' => [], 'properties' => []];

        foreach ($schema['allOf'] as $part) {
            $resolved = apiContractResolveSchema($part);
            $merged['required'] = array_values(array_unique(array_merge($merged['required'], $resolved['required'] ?? [])));
            $merged['properties'] = array_merge($merged['properties'], $resolved['properties'] ?? []);
        }

        return $merged;
    }

    return $schema;
}

function apiContractAssertObjectMatchesSchema(array $payload, string $schemaName): void
{
    $schema = apiContractSchema($schemaName);

    foreach ($schema['required'] ?? [] as $field) {
        expect(array_key_exists($field, $payload))->toBeTrue("Missing field {$schemaName}.{$field}");
    }

    foreach ($schema['properties'] ?? [] as $field => $property) {
        if (! array_key_exists($field, $payload)) {
            continue;
        }

        apiContractAssertType($payload[$field], $property, "{$schemaName}.{$field}");
    }
}

function apiContractAssertType(mixed $value, array $property, string $path): void
{
    if (isset($property['$ref'])) {
        if ($value === null) {
            return;
        }

        apiContractAssertObjectMatchesSchema($value, basename(str_replace('\\', '/', $property['$ref'])));

        return;
    }

    if (isset($property['oneOf'])) {
        if ($value === null) {
            return;
        }

        foreach ($property['oneOf'] as $option) {
            if (isset($option['$ref'])) {
                apiContractAssertObjectMatchesSchema($value, basename(str_replace('\\', '/', $option['$ref'])));

                return;
            }
        }
    }

    $nullable = (bool) ($property['nullable'] ?? false);

    if ($value === null) {
        expect($nullable)->toBeTrue("Unexpected null at {$path}");

        return;
    }

    $type = $property['type'] ?? null;

    match ($type) {
        'integer' => expect(is_int($value))->toBeTrue("Expected integer at {$path}"),
        'string' => expect(is_string($value))->toBeTrue("Expected string at {$path}"),
        'boolean' => expect(is_bool($value))->toBeTrue("Expected boolean at {$path}"),
        'array' => expect(is_array($value) && array_is_list($value))->toBeTrue("Expected array at {$path}"),
        'object' => expect(is_array($value))->toBeTrue("Expected object at {$path}"),
        default => null,
    };

    if (isset($property['enum'])) {
        expect($property['enum'])->toContain($value);
    }
}

function apiContractSeedFixture(): array
{
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);
    $loan = Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);
    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinute(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $member->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => NotificationType::RESERVATION_CREATED->value,
        'data' => [
            'kind' => NotificationType::RESERVATION_CREATED->value,
            'type' => NotificationType::RESERVATION_CREATED->value,
            'title' => 'Rezervacija',
            'message' => 'Rezervacija sukurta',
            'url' => '/notifications',
            'metadata' => ['reservation_id' => $reservation->id],
        ],
        'read_at' => null,
    ]);

    return compact('library', 'branch', 'admin', 'member', 'book', 'copy', 'loan', 'reservation');
}

it('documents critical API paths in OpenAPI', function () {
    $paths = array_keys(apiContractSpec()['paths']);

    expect($paths)->toContain('/auth/login')
        ->and($paths)->toContain('/auth/me')
        ->and($paths)->toContain('/auth/books')
        ->and($paths)->toContain('/auth/books/{book}')
        ->and($paths)->toContain('/auth/book-copies/{bookCopy}')
        ->and($paths)->toContain('/auth/reservations')
        ->and($paths)->toContain('/auth/reservations/{reservation}/cancel')
        ->and($paths)->toContain('/auth/loans/active')
        ->and($paths)->toContain('/auth/notifications');
});

it('keeps documented enum values aligned with model status enums', function () {
    expect(apiContractSchema('Reservation')['properties']['status']['enum'])
        ->toEqual(array_values(Reservation::statusLabels() ? array_keys(Reservation::statusLabels()) : []))
        ->and(apiContractSchema('Loan')['properties']['status']['enum'])
        ->toEqual(array_values(array_keys(Loan::statusLabels())))
        ->and(apiContractSchema('BookCopy')['properties']['status']['enum'])
        ->toEqual(array_values(array_keys(BookCopy::operationalStatusLabels())))
        ->and(apiContractSchema('BookCopy')['properties']['lifecycle_status']['enum'])
        ->toEqual(array_values(array_keys(BookCopy::statusLabels())));
});

it('matches authentication response contract', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);

    $payload = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()->json();

    expect($payload)->toHaveKeys(['message', 'token', 'user']);
    apiContractAssertObjectMatchesSchema($payload['user'], 'AuthUser');
});

it('matches book details and book copy contracts', function () {
    ['admin' => $admin, 'book' => $book, 'copy' => $copy] = apiContractSeedFixture();

    $bookPayload = $this->actingAs($admin)
        ->getJson("/api/auth/books/{$book->id}")
        ->assertOk()
        ->json();

    apiContractAssertObjectMatchesSchema($bookPayload, 'BookDetails');
    apiContractAssertObjectMatchesSchema(Arr::first($bookPayload['book_copies']), 'BookCopy');

    $copyPayload = $this->actingAs($admin)
        ->getJson("/api/auth/book-copies/{$copy->id}")
        ->assertOk()
        ->json();

    apiContractAssertObjectMatchesSchema($copyPayload, 'BookCopy');
});

it('matches paginated reservation loan and notification contracts', function () {
    ['admin' => $admin, 'member' => $member] = apiContractSeedFixture();

    $reservations = $this->actingAs($admin)
        ->getJson('/api/auth/reservations?per_page=1')
        ->assertOk()
        ->json();

    expect($reservations)->toHaveKeys(apiContractSchema('PaginatedReservations')['required']);
    apiContractAssertObjectMatchesSchema($reservations['data'][0], 'Reservation');
    apiContractAssertObjectMatchesSchema($reservations['links'], 'PaginationLinks');
    apiContractAssertObjectMatchesSchema($reservations['meta'], 'PaginationMeta');

    $loans = $this->actingAs($admin)
        ->getJson('/api/auth/loans/active?per_page=1')
        ->assertOk()
        ->json();

    expect($loans)->toHaveKeys(apiContractSchema('PaginatedLoans')['required']);
    apiContractAssertObjectMatchesSchema($loans['data'][0], 'Loan');
    apiContractAssertObjectMatchesSchema($loans['links'], 'PaginationLinks');
    apiContractAssertObjectMatchesSchema($loans['meta'], 'PaginationMeta');

    $notifications = $this->actingAs($member)
        ->getJson('/api/auth/notifications?per_page=1')
        ->assertOk()
        ->json();

    expect($notifications)->toHaveKeys(apiContractSchema('PaginatedNotifications')['required']);
    apiContractAssertObjectMatchesSchema($notifications['data'][0], 'UserNotification');
    apiContractAssertObjectMatchesSchema($notifications['links'], 'PaginationLinks');
    apiContractAssertObjectMatchesSchema($notifications['meta'], 'PaginationMeta');
});

it('documents standard validation error format', function () {
    $payload = $this->postJson('/api/auth/login', [
        'email' => 'not-an-email',
        'password' => '',
    ])->assertUnprocessable()->json();

    expect($payload)->toHaveKeys(apiContractSchema('ValidationError')['required'])
        ->and($payload['errors'])->toHaveKey('email')
        ->and($payload['errors'])->toHaveKey('password');
});
