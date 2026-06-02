<?php

namespace App\Services;

use App\Models\User;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use GuzzleHttp\Client;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmService
{
    private const FIREBASE_MESSAGING_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function __construct(
        private readonly mixed $accessTokenResolver = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sendToUser(User $user, string $title, string $message, array $data = []): array
    {
        $tokens = $user->deviceTokens()
            ->pluck('token')
            ->filter()
            ->values()
            ->all();

        return $this->sendToMany($tokens, $title, $message, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function sendToToken(string $token, string $title, string $message, array $data = []): array
    {
        $token = trim($token);

        if ($token === '') {
            return [
                'sent' => 0,
                'failed' => 0,
                'responses' => [],
            ];
        }

        $response = $this->http()
            ->post($this->endpoint(), [
                'message' => $this->buildMessage($token, $title, $message, $data),
            ])
            ->throw()
            ->json();

        return [
            'sent' => 1,
            'failed' => 0,
            'responses' => [$response],
        ];
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<string, mixed>
     */
    public function sendToMany(array $tokens, string $title, string $message, array $data = []): array
    {
        $tokens = collect($tokens)
            ->map(fn (string $token) => trim($token))
            ->filter()
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return [
                'sent' => 0,
                'failed' => 0,
                'responses' => [],
            ];
        }

        $responses = [];
        $failed = 0;

        foreach ($tokens as $token) {
            try {
                $result = $this->sendToToken($token, $title, $message, $data);
                $responses[] = Arr::first($result['responses']);
            } catch (\Throwable $exception) {
                $failed++;
                $responses[] = [
                    'token' => $token,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return [
            'sent' => $tokens->count() - $failed,
            'failed' => $failed,
            'responses' => $responses,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildMessage(string $token, string $title, string $message, array $data): array
    {
        $payload = array_merge([
            'title' => $title,
            'body' => $message,
            'type' => (string) ($data['type'] ?? 'notification'),
            'notification_id' => (string) ($data['notification_id'] ?? ''),
            'related_type' => (string) ($data['related_type'] ?? ''),
            'related_id' => (string) ($data['related_id'] ?? ''),
            'deep_link' => (string) ($data['deep_link'] ?? ''),
        ], $data);

        return [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $message,
            ],
            'data' => collect($payload)
                ->map(fn (mixed $value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value))
                ->all(),
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'library_notifications',
                ],
            ],
        ];
    }

    private function http(): PendingRequest
    {
        $request = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson();

        if ($caBundle = $this->caBundlePath()) {
            $request = $request->withOptions(['verify' => $caBundle]);
        }

        return $request;
    }

    private function endpoint(): string
    {
        $projectId = config('services.firebase.project_id');

        if (blank($projectId)) {
            throw new RuntimeException('Firebase project ID is not configured.');
        }

        return sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', $projectId);
    }

    private function accessToken(): string
    {
        if (is_callable($this->accessTokenResolver)) {
            return (string) call_user_func($this->accessTokenResolver);
        }

        return Cache::remember('firebase.access_token', now()->addMinutes(50), function (): string {
            $credentials = new ServiceAccountCredentials(
                self::FIREBASE_MESSAGING_SCOPE,
                $this->credentialsPath()
            );

            $httpHandler = null;

            if ($caBundle = $this->caBundlePath()) {
                $httpHandler = HttpHandlerFactory::build(new Client([
                    'verify' => $caBundle,
                ]));
            }

            $token = $credentials->fetchAuthToken($httpHandler);

            if (empty($token['access_token'])) {
                throw new RuntimeException('Firebase access token could not be fetched.');
            }

            return $token['access_token'];
        });
    }

    private function credentialsPath(): string
    {
        $path = config('services.firebase.credentials');

        if (blank($path)) {
            throw new RuntimeException('Firebase credentials path is not configured.');
        }

        $path = (string) $path;

        if (! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) && ! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }

        return $path;
    }

    private function caBundlePath(): ?string
    {
        $path = config('services.firebase.ca_bundle');

        if (blank($path)) {
            return null;
        }

        $path = (string) $path;

        if (! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) && ! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }

        return $path;
    }
}
