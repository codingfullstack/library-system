<?php

namespace App\Support\Tenancy;

final class TenantIntegrityViolation
{
    public function __construct(
        public readonly string $table,
        public readonly int $id,
        public readonly string $type,
        public readonly ?int $libraryId = null,
        public readonly ?string $relatedTable = null,
        public readonly ?int $relatedId = null,
    ) {}

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'id' => $this->id,
            'type' => $this->type,
            'library_id' => $this->libraryId,
            'related_table' => $this->relatedTable,
            'related_id' => $this->relatedId,
        ];
    }
}
