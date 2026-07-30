<?php

namespace App\Support;

use App\Models\User;
use App\Models\LibraryMembership;
use App\Models\Reservation;
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
            ->when($actor->role === User::ROLE_STAFF, function (Builder $builder) use ($actor) {
                $libraryId = $actor->activeLibraryId();
                $branchId = $actor->assignedBranchId($libraryId);

                $builder->where(function (Builder $activityQuery) use ($libraryId, $branchId) {
                    if ($branchId === null) {
                        $activityQuery->whereRaw('1 = 0');

                        return;
                    }

                    $activityQuery
                        ->whereHas('loans.bookCopy', fn (Builder $copyQuery) => $copyQuery
                            ->where('library_id', $libraryId)
                            ->where('branch_id', $branchId))
                        ->orWhereHas('reservations', function (Builder $reservationQuery) use ($libraryId, $branchId) {
                            $reservationQuery
                                ->where('library_id', $libraryId)
                                ->where(function (Builder $scopeQuery) use ($branchId) {
                                    $scopeQuery
                                        ->where(function (Builder $libraryScopeQuery) {
                                            $libraryScopeQuery
                                                ->where('scope', Reservation::SCOPE_LIBRARY)
                                                ->whereNull('branch_id');
                                        })
                                        ->orWhere(function (Builder $branchScopeQuery) use ($branchId) {
                                            $branchScopeQuery
                                                ->where('scope', Reservation::SCOPE_BRANCH)
                                                ->where('branch_id', $branchId);
                                        });
                                });
                        });
                });
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

    public static function syncLibraryMembership(User $user, int $libraryId, ?int $branchId = null): LibraryMembership
    {
        if ($user->isSuperAdmin()) {
            throw new \InvalidArgumentException('Superadministratoriui bibliotekos narystė nepriskiriama.');
        }

        if ($user->role === User::ROLE_STAFF) {
            if ($branchId === null) {
                throw new \InvalidArgumentException('Darbuotojo narystei privalomas filialas.');
            }

            $branchBelongsToLibrary = \App\Models\Branch::query()
                ->whereKey($branchId)
                ->where('library_id', $libraryId)
                ->exists();

            if (! $branchBelongsToLibrary) {
                throw new \InvalidArgumentException('Darbuotojo filialas turi priklausyti tai pačiai bibliotekai.');
            }
        }

        return LibraryMembership::query()->updateOrCreate(
            [
                'library_id' => $libraryId,
                'user_id' => $user->id,
            ],
            [
                'branch_id' => $user->role === User::ROLE_STAFF ? $branchId : null,
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








