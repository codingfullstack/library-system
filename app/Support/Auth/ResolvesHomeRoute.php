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

        if (in_array($user->role, ['super_admin', 'admin', 'staff'], true)) {
            return 'dashboard';
        }

        if ($user->role === 'member') {
            return 'account.dashboard';
        }

        return 'books.index';
    }
}
