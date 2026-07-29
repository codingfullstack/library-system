<?php

use App\Services\FcmService;
use App\Support\Observability\OperationDiagnostics;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

it('attaches a request id to api responses', function () {
    $this->getJson('/api/auth/me')
        ->assertHeader('X-Request-Id');
});

it('preserves an incoming request id', function () {
    $this->withHeader('X-Request-Id', 'request-123')
        ->getJson('/api/auth/me')
        ->assertHeader('X-Request-Id', 'request-123');
});

it('does not expose raw fcm tokens in failure responses', function () {
    config(['services.firebase.project_id' => 'library-project']);

    Http::fake([
        'https://fcm.googleapis.com/v1/projects/library-project/messages:send' => Http::response([
            'error' => ['message' => 'unavailable'],
        ], 503),
    ]);

    $result = (new FcmService(fn () => 'fake-access-token'))
        ->sendToMany(['device-token-1'], 'Title', 'Body');

    expect($result['failed'])->toBe(1)
        ->and($result['responses'][0])->toHaveKey('token_hash')
        ->and($result['responses'][0])->not->toHaveKey('token')
        ->and($result['responses'][0]['token_hash'])->not->toBe('device-token-1');
});

it('does not let diagnostics logging failures interrupt domain code', function () {
    Log::shouldReceive('error')->once()->andThrow(new RuntimeException('logger unavailable'));
    Log::shouldReceive('warning')->once()->andThrow(new RuntimeException('logger unavailable'));

    $diagnostics = new OperationDiagnostics();

    $diagnostics->failure('reservation_cancel_failed', new RuntimeException('domain failure'), [
        'operation' => 'reservation_cancel',
        'reservation_id' => 10,
    ]);
    $diagnostics->warning('tenant_integrity_preflight_failed', [
        'operation' => 'tenant_integrity_preflight',
        'violation_count' => 1,
    ]);

    expect(true)->toBeTrue();
});
