<?php

namespace App\Actions\AuditLogs;

use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Publisher;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RecordAuditLogAction
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        ?User $actor,
        string $action,
        ?Model $auditable,
        string $description,
        array $metadata = [],
        ?int $libraryId = null
    ): AuditLog {
        $metadata = $this->withAuditContext($metadata, $actor, $auditable);

        return AuditLog::create([
            'user_id' => $actor?->id,
            'library_id' => $libraryId ?? $this->resolveLibraryId($actor, $auditable),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    private function resolveLibraryId(?User $actor, ?Model $auditable): ?int
    {
        $candidate = $auditable?->getAttribute('library_id') ?? $actor?->activeLibraryId();

        return $candidate ? (int) $candidate : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function withAuditContext(array $metadata, ?User $actor, ?Model $auditable): array
    {
        return array_filter([
            ...$metadata,
            'actor_snapshot' => $metadata['actor_snapshot'] ?? $this->actorSnapshot($actor),
            'auditable_snapshot' => $metadata['auditable_snapshot'] ?? $this->auditableSnapshot($auditable),
            'request_context' => $metadata['request_context'] ?? $this->requestContext(),
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @return array<string, string>|null
     */
    private function actorSnapshot(?User $actor): ?array
    {
        if (! $actor) {
            return null;
        }

        return array_filter([
            'name' => $actor->name,
            'email' => $actor->email,
            'role' => $actor->role,
        ], fn ($value) => filled($value));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function auditableSnapshot(?Model $auditable): ?array
    {
        if (! $auditable) {
            return null;
        }

        return array_filter([
            'type' => $auditable->getMorphClass(),
            'type_label' => $this->auditableTypeLabel($auditable),
            'id' => $auditable->getKey(),
            'label' => $this->auditableLabel($auditable),
        ], fn ($value) => filled($value));
    }

    private function auditableTypeLabel(Model $auditable): string
    {
        return match (true) {
            $auditable instanceof Author => 'Autorius',
            $auditable instanceof Book => 'Knyga',
            $auditable instanceof BookCopy => 'Kopija',
            $auditable instanceof Branch => 'Filialas',
            $auditable instanceof Category => 'Kategorija',
            $auditable instanceof Library => 'Biblioteka',
            $auditable instanceof Loan => 'Išdavimas',
            $auditable instanceof Location => 'Vieta',
            $auditable instanceof Publisher => 'Leidykla',
            $auditable instanceof Reservation => 'Rezervacija',
            $auditable instanceof User => 'Vartotojas',
            default => class_basename($auditable),
        };
    }

    private function auditableLabel(Model $auditable): ?string
    {
        $attributes = $auditable->getAttributes();

        foreach (['title', 'name', 'inventory_code', 'code', 'email', 'membership_number'] as $field) {
            if (filled(Arr::get($attributes, $field))) {
                return (string) Arr::get($attributes, $field);
            }
        }

        if ($auditable instanceof Loan && filled($auditable->bookCopy?->inventory_code)) {
            return 'Išdavimas '.$auditable->bookCopy->inventory_code;
        }

        if ($auditable instanceof Reservation && filled($auditable->book?->title)) {
            return 'Rezervacija: '.$auditable->book->title;
        }

        return null;
    }

    /**
     * @return array<string, string>|null
     */
    private function requestContext(): ?array
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        if (! $request instanceof Request) {
            return null;
        }

        return array_filter([
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => Str::limit($request->path(), 120, ''),
            'route' => $request->route()?->getName(),
            'user_agent' => Str::limit((string) $request->userAgent(), 180, ''),
        ], fn ($value) => filled($value));
    }
}








