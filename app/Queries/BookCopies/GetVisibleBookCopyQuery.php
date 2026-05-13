<?php

namespace App\Queries\BookCopies;

use App\Models\BookCopy;
use App\Models\User;

class GetVisibleBookCopyQuery
{
    public function handle(User $user, string|int $id, array $with = []): BookCopy
    {
        return BookCopy::query()
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->where('library_id', $user->activeLibraryId()))
            ->with($with)
            ->findOrFail($id);
    }
}








