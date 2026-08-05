<?php

namespace App\Support\Observability;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationDiagnostics
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function failure(string $event, Throwable $exception, array $context = []): void
    {
        try {
            Log::error($event, $this->failureContext($exception, $context));
        } catch (Throwable) {
            // Diagnostics must never change domain transaction outcomes.
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = []): void
    {
        try {
            Log::warning($event, $this->sanitize($context));
        } catch (Throwable) {
            // Diagnostics must never change domain transaction outcomes.
        }
    }

    public function tokenHash(string $token): string
    {
        return substr(hash('sha256', $token), 0, 16);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function failureContext(Throwable $exception, array $context): array
    {
        $payload = array_merge($context, [
            'exception_class' => $exception::class,
            'is_deadlock' => $this->isDeadlock($exception),
        ]);

        if ($exception instanceof QueryException) {
            $payload['sql_state'] = $exception->errorInfo[0] ?? null;
            $payload['database_error_code'] = $exception->errorInfo[1] ?? null;
        }

        return $this->sanitize($payload);
    }

    private function isDeadlock(Throwable $exception): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        return (string) ($exception->errorInfo[0] ?? '') === '40001'
            || (int) ($exception->errorInfo[1] ?? 0) === 1213
            || str_contains(strtolower($exception->getMessage()), 'deadlock');
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitize(array $context): array
    {
        $allowed = [
            'operation',
            'library_id',
            'book_id',
            'book_copy_id',
            'reservation_id',
            'loan_id',
            'request_id',
            'job_id',
            'sql_state',
            'database_error_code',
            'exception_class',
            'is_deadlock',
            'failed',
            'sent',
            'token_hash',
            'violation_count',
            'violation_types',
        ];

        return collect($context)
            ->only($allowed)
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->all();
    }
}
