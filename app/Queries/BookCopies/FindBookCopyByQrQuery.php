<?php

namespace App\Queries\BookCopies;

use App\Models\BookCopy;
use App\Models\User;

class FindBookCopyByQrQuery
{
    /**
     * @param  array<int, string>  $relations
     */
    public function handle(User $user, string $qrCode, array $relations = []): ?BookCopy
    {
        $query = BookCopy::query()
            ->with($relations)
            ->where('qr_code', $qrCode);

        if (! $user->isSuperAdmin()) {
            $query->where('library_id', $user->activeLibraryId());
        }

        return $query->first();
    }
}








