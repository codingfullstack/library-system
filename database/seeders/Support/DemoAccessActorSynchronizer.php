<?php

namespace Database\Seeders\Support;

use App\Models\Branch;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\User;
use App\Support\UserManagement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class DemoAccessActorSynchronizer
{
    /**
     * @return array{admins: Collection<int, User>, staff: Collection<int, User>, members: Collection<int, User>}
     */
    public function syncLibrary(Library $library): array
    {
        $definition = config("demo.libraries.{$library->code}");

        if (! is_array($definition)) {
            return [
                'admins' => collect(),
                'staff' => collect(),
                'members' => collect(),
            ];
        }

        return DB::transaction(function () use ($library, $definition) {
            $this->syncSuperadmins();

            $admins = collect($definition['admins'] ?? [])
                ->map(fn (array $actor) => $this->syncActor($library, $actor, User::ROLE_ADMIN));
            $staff = collect($definition['staff'] ?? [])
                ->map(fn (array $actor) => $this->syncActor($library, $actor, User::ROLE_STAFF));
            $members = collect($definition['members'] ?? [])
                ->map(fn (array $actor) => $this->syncActor($library, $actor, User::ROLE_MEMBER));

            return [
                'admins' => $admins->values(),
                'staff' => $staff->values(),
                'members' => $members->values(),
            ];
        });
    }

    /**
     * @return Collection<int, User>
     */
    public function syncSuperadmins(): Collection
    {
        return collect(config('demo.superadmins', []))
            ->map(function (array $actor) {
                $user = User::query()->updateOrCreate(
                    ['email' => $actor['email']],
                    [
                        'name' => $actor['name'],
                        'password' => Hash::make(config('demo.password', 'password')),
                        'role' => User::ROLE_SUPER_ADMIN,
                        'phone' => $actor['phone'] ?? null,
                        'membership_number' => null,
                        'is_active' => (bool) ($actor['is_active'] ?? true),
                        'email_verified_at' => now(),
                    ]
                );

                LibraryMembership::query()
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false, 'branch_id' => null]);

                return $user;
            })
            ->values();
    }

    public function syncActor(Library $library, array $actor, string $role): User
    {
        $isActive = (bool) ($actor['is_active'] ?? true);
        $membershipNumber = $role === User::ROLE_MEMBER
            ? $this->membershipNumberFor($actor['email'])
            : null;

        $user = User::query()->updateOrCreate(
            ['email' => $actor['email']],
            [
                'name' => $actor['name'],
                'password' => Hash::make(config('demo.password', 'password')),
                'role' => $role,
                'phone' => $actor['phone'] ?? null,
                'membership_number' => $membershipNumber,
                'is_active' => $isActive,
                'email_verified_at' => now(),
            ]
        );

        $branch = $role === User::ROLE_STAFF
            ? $this->resolveStaffBranch($library, $actor)
            : null;

        UserManagement::syncLibraryMembership($user, $library->id, $branch?->id)
            ->forceFill([
                'membership_number' => $user->membership_number,
                'is_active' => $isActive,
                'joined_at' => $user->created_at ?? now(),
            ])
            ->save();

        $this->deactivateContradictingDemoMemberships($user, $library);

        return $user->refresh();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function expectedActors(): Collection
    {
        $libraries = collect(config('demo.libraries', []))
            ->flatMap(function (array $definition, string $libraryCode) {
                return collect([
                    'admins' => User::ROLE_ADMIN,
                    'staff' => User::ROLE_STAFF,
                    'members' => User::ROLE_MEMBER,
                ])->flatMap(function (string $role, string $group) use ($definition, $libraryCode) {
                    return collect($definition[$group] ?? [])
                        ->map(fn (array $actor) => $actor + [
                            'library_code' => $libraryCode,
                            'role' => $role,
                            'branch_code' => $role === User::ROLE_STAFF ? ($actor['branch_code'] ?? null) : null,
                        ]);
                });
            });

        $superadmins = collect(config('demo.superadmins', []))
            ->map(fn (array $actor) => $actor + [
                'library_code' => null,
                'role' => User::ROLE_SUPER_ADMIN,
                'branch_code' => null,
            ]);

        return $superadmins->merge($libraries)->values();
    }

    public function resolveStaffBranch(Library $library, array $actor): Branch
    {
        $branchCode = $actor['branch_code'] ?? null;
        $email = $actor['email'] ?? 'unknown';

        if (! is_string($branchCode) || $branchCode === '') {
            throw new InvalidArgumentException(sprintf(
                'Demo staff "%s" in library "%s" must declare a non-empty branch_code; received "%s".',
                $email,
                $library->code,
                is_scalar($branchCode) ? (string) $branchCode : get_debug_type($branchCode)
            ));
        }

        $branch = Branch::query()
            ->where('library_id', $library->id)
            ->where('code', $branchCode)
            ->first();

        if (! $branch) {
            $foreignBranch = Branch::query()
                ->where('code', $branchCode)
                ->with('library:id,code')
                ->first();

            if ($foreignBranch) {
                throw new InvalidArgumentException(sprintf(
                    'Demo staff "%s" in library "%s" references branch_code "%s", but that branch belongs to library "%s".',
                    $email,
                    $library->code,
                    $branchCode,
                    $foreignBranch->library?->code ?? $foreignBranch->library_id
                ));
            }

            throw new InvalidArgumentException(sprintf(
                'Demo staff "%s" in library "%s" references missing branch_code "%s".',
                $email,
                $library->code,
                $branchCode,
            ));
        }

        if (Schema::hasColumn('branches', 'is_active') && ! (bool) $branch->getAttribute('is_active')) {
            throw new InvalidArgumentException(sprintf(
                'Demo staff "%s" in library "%s" references inactive branch_code "%s".',
                $email,
                $library->code,
                $branchCode
            ));
        }

        return $branch;
    }

    private function membershipNumberFor(string $email): string
    {
        $existing = User::query()->where('email', $email)->value('membership_number');

        return str_starts_with((string) $existing, 'MEM:')
            ? $existing
            : UserManagement::generateMembershipNumber();
    }

    private function deactivateContradictingDemoMemberships(User $user, Library $declaredLibrary): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        LibraryMembership::query()
            ->where('user_id', $user->id)
            ->where('library_id', '<>', $declaredLibrary->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'branch_id' => null,
            ]);
    }
}
