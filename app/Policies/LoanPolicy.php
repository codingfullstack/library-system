<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;
use App\Models\BookCopy;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin', 'staff', 'member'], true);
    }

    public function view(User $user, Loan $loan): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->role === 'member') {
            return $loan->user_id === $user->id
                && $loan->library_id === $user->library_id;
        }

        return $user->belongsToLibrary($loan->library_id);
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

    public function delete(User $user, Loan $loan): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->role === 'admin'
            && $user->belongsToLibrary($loan->library_id);
    }
}