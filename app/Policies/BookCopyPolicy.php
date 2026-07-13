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
        return $user->canManageBookCopy($bookCopy);
    }

    public function transfer(User $user, BookCopy $bookCopy, mixed $targetBranch = null): bool
    {
        return app(\App\Services\BookCopyBranchTransferService::class)
            ->canTransfer($user, $bookCopy, $targetBranch);
    }

    public function delete(User $user, BookCopy $bookCopy): bool
    {
        return $user->canManageBookCopy($bookCopy);
    }

    public function borrow(User $user, BookCopy $bookCopy): bool
    {
        return $user->canManageBookCopy($bookCopy);
    }

    public function return(User $user, BookCopy $bookCopy): bool
    {
        return $user->canManageBookCopy($bookCopy);
    }
}








