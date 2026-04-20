<?php

namespace App\Policies;

use App\Models\BookCopy;
use App\Models\User;

class BookCopyPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin', 'staff'], true);
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
        return in_array($user->role, ['super_admin', 'admin', 'staff'], true);
    }

    public function update(User $user, BookCopy $bookCopy): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($user->role, ['admin', 'staff'], true)
            && $user->belongsToLibrary($bookCopy->library_id);
    }

    public function delete(User $user, BookCopy $bookCopy): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->role === 'admin'
            && $user->belongsToLibrary($bookCopy->library_id);
    }
}