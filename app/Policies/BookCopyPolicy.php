<?php

namespace App\Policies;

use App\Models\BookCopy;
use App\Models\User;

class BookCopyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyEffectiveRole(['superadministratorius', 'administratorius', 'darbuotojas']);
    }

    public function view(User $user, BookCopy $bookCopy): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->belongsToLibrary($bookCopy->library_id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyEffectiveRole(['superadministratorius', 'administratorius', 'darbuotojas']);
    }

    public function update(User $user, BookCopy $bookCopy): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyEffectiveRole(['administratorius', 'darbuotojas'], $bookCopy->library_id)
            && $user->belongsToLibrary($bookCopy->library_id);
    }

    public function delete(User $user, BookCopy $bookCopy): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyEffectiveRole(['administratorius', 'darbuotojas'], $bookCopy->library_id)
            && $user->belongsToLibrary($bookCopy->library_id);
    }
}








