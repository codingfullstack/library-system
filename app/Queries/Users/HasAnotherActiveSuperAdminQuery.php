<?php

namespace App\Queries\Users;

use App\Models\User;

class HasAnotherActiveSuperAdminQuery
{
    public function handle(User $user): bool
    {
        return User::query()
            ->where('role', 'superadministratorius')
            ->whereKeyNot($user->id)
            ->where('is_active', true)
            ->exists();
    }
}








