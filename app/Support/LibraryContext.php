<?php

namespace App\Support;

class LibraryContext
{
    protected ?int $libraryId = null;
    protected bool $isSuperAdmin = false;

    public function set(?int $libraryId, bool $isSuperAdmin = false): void
    {
        $this->libraryId = $libraryId;
        $this->isSuperAdmin = $isSuperAdmin;
    }

    public function libraryId(): ?int
    {
        return $this->libraryId;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    public function hasLibrary(): bool
    {
        return $this->libraryId !== null;
    }

    public function clear(): void
    {
        $this->libraryId = null;
        $this->isSuperAdmin = false;
    }
}







