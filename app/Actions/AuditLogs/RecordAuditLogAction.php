<?php

namespace App\Actions\AuditLogs;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

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
        return AuditLog::create([
            'user_id' => $actor?->id,
            'library_id' => $libraryId ?? $this->resolveLibraryId($actor, $auditable),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    private function resolveLibraryId(?User $actor, ?Model $auditable): ?int
    {
        $candidate = $auditable?->getAttribute('library_id') ?? $actor?->library_id;

        return $candidate ? (int) $candidate : null;
    }
}
