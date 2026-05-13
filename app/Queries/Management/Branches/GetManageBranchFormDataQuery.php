<?php

namespace App\Queries\Management\Branches;

use App\Models\Branch;
use App\Models\Library;
use App\Models\User;

class GetManageBranchFormDataQuery
{
    public function handle(User $user, Branch $branch): array
    {
        return [
            'branch' => $branch,
            'libraries' => $user->isSuperAdmin()
                ? Library::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ];
    }
}








