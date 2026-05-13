<?php

namespace App\Support;

use App\Models\User;
use App\Models\LibraryMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class UserManagement
{
    public static function manageableRoles(User $actor): array
    {
        return match ($actor->role) {
            'superadministratorius' => ['superadministratorius', 'administratorius', 'darbuotojas', 'narys'],
            'administratorius' => ['administratorius', 'darbuotojas', 'narys'],
            'darbuotojas' => ['narys'],
            default => [],
        };
    }

    public static function canManageRole(User $actor, string $role): bool
    {
        return in_array($role, self::manageableRoles($actor), true);
    }

    public static function scopeVisibleUsers(Builder $query, User $actor): Builder
    {
        $roles = self::manageableRoles($actor);
        $libraryIds = $actor->manageableLibraryIds();

        return $query
            ->when(! $actor->isSuperAdmin(), function (Builder $builder) use ($libraryIds) {
                $builder->whereHas('libraryMemberships', fn (Builder $membershipQuery) => $membershipQuery
                    ->whereIn('library_id', $libraryIds)
                    ->where('is_active', true));
            })
            ->whereIn('role', $roles);
    }

    public static function canManageUser(User $actor, User $target): bool
    {
        if (! self::canManageRole($actor, $target->role)) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        return collect($actor->manageableLibraryIds())
            ->contains(fn (int $libraryId) => $target->belongsToLibrary($libraryId));
    }

    public static function requiresLibrary(string $role): bool
    {
        return $role !== 'superadministratorius';
    }

    public static function defaultRole(User $actor): string
    {
        return match ($actor->role) {
            'superadministratorius', 'administratorius', 'darbuotojas' => 'narys',
            default => 'narys',
        };
    }

    public static function generateMembershipNumber(): string
    {
        do {
            $candidate = 'MEM:' . (string) Str::ulid();
        } while (User::query()->where('membership_number', $candidate)->exists());

        return $candidate;
    }

    public static function syncLibraryMembership(User $user, int $libraryId): LibraryMembership
    {
        if ($user->isSuperAdmin()) {
            throw new \InvalidArgumentException('Superadministratoriui bibliotekos narystė nepriskiriama.');
        }

        return LibraryMembership::query()->updateOrCreate(
            [
                'library_id' => $libraryId,
                'user_id' => $user->id,
            ],
            [
                'membership_number' => $user->membership_number,
                'is_active' => $user->is_active,
                'joined_at' => $user->created_at,
            ]
        );
    }

    public static function syncUserMembershipActivity(User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $user->libraryMemberships()->update([
            'is_active' => $user->is_active,
        ]);
    }
}








