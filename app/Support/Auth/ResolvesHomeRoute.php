<?php

namespace App\Support\Auth;

use App\Models\User;

class ResolvesHomeRoute
{
    public static function routeName(?User $user): string
    {
        if (! $user) {
            return 'home';
        }

        if ($user->hasStaffAccess()) {
            return 'dashboard';
        }

        $libraryId = $user->activeLibraryId();

        if ($user->effectiveRole($libraryId) === User::ROLE_MEMBER) {
            return 'account.dashboard';
        }

        return 'books.index';
    }
}
