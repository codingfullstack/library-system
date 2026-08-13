<?php

namespace App\Support\Reports;

use Illuminate\Support\Collection;

class DashboardScope
{
    /**
     * @param  Collection<int, object{id:int, name:string}>  $branchOptions
     */
    public function __construct(
        public readonly ?int $libraryId,
        public readonly ?string $libraryName,
        public readonly ?string $libraryCode,
        public readonly ?int $branchId,
        public readonly ?string $branchName,
        public readonly string $type,
        public readonly bool $canSelectBranch,
        public readonly Collection $branchOptions,
        public readonly bool $isGlobal = false,
    ) {}

    public function isLibrary(): bool
    {
        return $this->type === 'library';
    }

    public function isBranch(): bool
    {
        return $this->type === 'branch';
    }

    public function label(): string
    {
        if ($this->isGlobal) {
            return 'Visu biblioteku statistika';
        }

        if ($this->isBranch()) {
            return trim(($this->libraryName ?: 'Biblioteka').' - filialas: '.($this->branchName ?: 'nezinomas filialas'));
        }

        return ($this->libraryName ?: 'Biblioteka').' - visa biblioteka';
    }

    public function filenameScope(): string
    {
        $library = $this->libraryCode ?: $this->libraryName ?: ($this->isGlobal ? 'visos-bibliotekos' : 'biblioteka');

        if (! $this->isBranch()) {
            return $library;
        }

        return $library.'-'.$this->branchName;
    }
}
