<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;
use App\Models\BookCopy;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyEffectiveRole(['superadministratorius', 'administratorius', 'darbuotojas', 'narys']);
    }

    public function view(User $user, Loan $loan): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->effectiveRole($loan->library_id) === 'narys') {
            return $loan->user_id === $user->id
                && $user->belongsToLibrary($loan->library_id);
        }

        return $user->belongsToLibrary($loan->library_id);
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

    public function delete(User $user, Loan $loan): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAnyEffectiveRole(['administratorius'], $loan->library_id)
            && $user->belongsToLibrary($loan->library_id);
    }
}








