<?php

namespace App\Queries\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class SearchLibraryMembersQuery
{
    public function handle(User $authUser, string $query = ''): Collection
    {
        $query = trim($query);

        return User::query()
            ->with('libraryMemberships.library:id,name,code')
            ->when(! $authUser->isSuperAdmin(), function ($builder) use ($authUser) {
                $libraryId = $authUser->activeLibraryId();

                $builder->whereHas('libraryMemberships', fn ($membershipQuery) => $membershipQuery
                    ->where('library_id', $libraryId)
                    ->where('is_active', true));
            })
            ->where('role', 'narys')
            ->where('is_active', true)
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('membership_number', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get([
                'id',
                'name',
                'email',
                'membership_number',
                'phone',
            ]);
    }
}








