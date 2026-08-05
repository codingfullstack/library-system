<?php

namespace App\Support;

use App\Models\LibraryMembership;

class LibraryJoinResult
{
    private function __construct(
        public readonly string $status,
        public readonly LibraryMembership $membership,
        public readonly bool $created,
    ) {}

    public static function created(LibraryMembership $membership): self
    {
        return new self(LibraryJoinService::STATUS_ACTIVE, $membership, true);
    }

    public static function active(LibraryMembership $membership): self
    {
        return new self(LibraryJoinService::STATUS_ACTIVE, $membership, false);
    }

    public static function inactive(LibraryMembership $membership): self
    {
        return new self(LibraryJoinService::STATUS_INACTIVE, $membership, false);
    }

    public function isInactive(): bool
    {
        return $this->status === LibraryJoinService::STATUS_INACTIVE;
    }
}
