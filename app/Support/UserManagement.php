<?php

namespace App\Support;

use App\Models\User;
use App\Models\Library;
use Illuminate\Database\Eloquent\Builder;

class UserManagement
{
    public static function manageableRoles(User $actor): array
    {
        return match ($actor->role) {
            'super_admin' => ['super_admin', 'admin', 'staff', 'member'],
            'admin' => ['admin', 'staff', 'member'],
            'staff' => ['member'],
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

        return $query
            ->when(! $actor->isSuperAdmin(), fn (Builder $builder) => $builder->where('library_id', $actor->library_id))
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

        return (int) $target->library_id === (int) $actor->library_id;
    }

    public static function requiresLibrary(string $role): bool
    {
        return $role !== 'super_admin';
    }

    public static function defaultRole(User $actor): string
    {
        return match ($actor->role) {
            'super_admin', 'admin', 'staff' => 'member',
            default => 'member',
        };
    }

    public static function generateMembershipNumber(int $libraryId): string
    {
        $library = Library::query()->findOrFail($libraryId);
        $prefix = strtoupper($library->code ?: ('LIB' . $library->id));
        $base = $prefix . '-MEM-';

        $lastNumber = User::query()
            ->where('library_id', $libraryId)
            ->where('role', 'member')
            ->whereNotNull('membership_number')
            ->where('membership_number', 'like', $base . '%')
            ->get(['membership_number'])
            ->map(function ($user) use ($base) {
                return (int) str_replace($base, '', (string) $user->membership_number);
            })
            ->max() ?? 0;

        do {
            $lastNumber++;
            $candidate = $base . str_pad((string) $lastNumber, 3, '0', STR_PAD_LEFT);
        } while (
            User::query()
                ->where('library_id', $libraryId)
                ->where('membership_number', $candidate)
                ->exists()
        );

        return $candidate;
    }
}
