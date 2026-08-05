<?php

namespace App\Console\Commands;

use App\Models\LibraryMembership;
use App\Models\User;
use Database\Seeders\Support\DemoAccessActorSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditUserAccessCommand extends Command
{
    protected $signature = 'system:audit-user-access';

    protected $description = 'Audit user role, library membership, branch scope, and access invariants.';

    public function handle(): int
    {
        $details = [
            'membership_role_column_exists' => Schema::hasColumn('library_memberships', 'role')
                ? collect([(object) ['id' => 'library_memberships.role']])
                : collect(),
            'duplicate_users' => DB::table('users')
                ->select('email', DB::raw('count(*) as count'))
                ->groupBy('email')
                ->havingRaw('count(*) > 1')
                ->get(),
            'duplicate_memberships' => DB::table('library_memberships')
                ->select('library_id', 'user_id', DB::raw('count(*) as count'))
                ->groupBy('library_id', 'user_id')
                ->havingRaw('count(*) > 1')
                ->get(),
            'active_admin_without_membership' => User::query()
                ->select('users.id', 'users.email')
                ->where('role', User::ROLE_ADMIN)
                ->where('is_active', true)
                ->whereDoesntHave('libraryMemberships', fn ($query) => $query->where('is_active', true))
                ->get(),
            'active_staff_without_branch' => User::query()
                ->select('users.id', 'users.email')
                ->where('role', User::ROLE_STAFF)
                ->where('is_active', true)
                ->whereHas('libraryMemberships', fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('branch_id'))
                ->get(),
            'active_staff_without_membership' => User::query()
                ->select('users.id', 'users.email')
                ->where('role', User::ROLE_STAFF)
                ->where('is_active', true)
                ->whereDoesntHave('libraryMemberships', fn ($query) => $query->where('is_active', true))
                ->get(),
            'staff_branch_library_mismatch' => DB::table('library_memberships')
                ->select('library_memberships.id', 'library_memberships.user_id', 'library_memberships.library_id', 'library_memberships.branch_id')
                ->join('users', 'users.id', '=', 'library_memberships.user_id')
                ->leftJoin('branches', 'branches.id', '=', 'library_memberships.branch_id')
                ->where('users.role', User::ROLE_STAFF)
                ->where('users.is_active', true)
                ->where('library_memberships.is_active', true)
                ->whereNotNull('library_memberships.branch_id')
                ->whereColumn('branches.library_id', '<>', 'library_memberships.library_id')
                ->get(),
            'active_staff_with_inactive_branch' => $this->activeStaffWithInactiveBranchViolations(),
            'active_admin_with_branch_scope' => User::query()
                ->select('users.id', 'users.email')
                ->where('role', User::ROLE_ADMIN)
                ->where('is_active', true)
                ->whereHas('libraryMemberships', fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNotNull('branch_id'))
                ->get(),
            'active_member_with_branch_scope' => User::query()
                ->select('users.id', 'users.email')
                ->where('role', User::ROLE_MEMBER)
                ->where('is_active', true)
                ->whereHas('libraryMemberships', fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNotNull('branch_id'))
                ->get(),
            'active_non_superadmin_without_membership' => User::query()
                ->select('users.id', 'users.email', 'users.role')
                ->where('role', '<>', User::ROLE_SUPER_ADMIN)
                ->where('is_active', true)
                ->whereDoesntHave('libraryMemberships', fn ($query) => $query->where('is_active', true))
                ->get(),
            'active_membership_to_inactive_library' => DB::table('library_memberships')
                ->select('library_memberships.id', 'library_memberships.user_id', 'library_memberships.library_id')
                ->join('libraries', 'libraries.id', '=', 'library_memberships.library_id')
                ->where('library_memberships.is_active', true)
                ->where('libraries.is_active', false)
                ->get(),
            'inactive_user_with_active_membership' => DB::table('library_memberships')
                ->select('library_memberships.id', 'library_memberships.user_id', 'library_memberships.library_id')
                ->join('users', 'users.id', '=', 'library_memberships.user_id')
                ->where('users.is_active', false)
                ->where('library_memberships.is_active', true)
                ->get(),
            'inactive_membership_with_effective_role' => $this->inactiveMembershipsWithEffectiveRole(),
            'inactive_user_with_effective_role' => $this->inactiveUsersWithEffectiveRole(),
            'active_demo_library_without_admin' => $this->demoLibrariesWithoutAdmin(),
            'active_branch_without_staff' => $this->activeBranchesWithoutStaff(),
            'stale_demo_memberships' => $this->staleDemoMemberships(),
            'orphan_loans' => DB::table('loans')
                ->select('loans.id', 'loans.library_id', 'loans.user_id')
                ->leftJoin('library_memberships', function ($join) {
                    $join->on('library_memberships.library_id', '=', 'loans.library_id')
                        ->on('library_memberships.user_id', '=', 'loans.user_id');
                })
                ->whereNull('library_memberships.id')
                ->get(),
            'orphan_reservations' => DB::table('reservations')
                ->select('reservations.id', 'reservations.library_id', 'reservations.user_id')
                ->leftJoin('library_memberships', function ($join) {
                    $join->on('library_memberships.library_id', '=', 'reservations.library_id')
                        ->on('library_memberships.user_id', '=', 'reservations.user_id');
                })
                ->whereNull('library_memberships.id')
                ->get(),
            'declared_demo_access_invalid' => $this->demoAccessViolations(),
        ];

        $this->table(['Invariant', 'Violations', 'Details'], collect($details)->map(
            fn (Collection $rows, string $name) => [$name, $rows->count(), $this->formatDetails($rows)]
        ));

        collect($details)
            ->filter(fn (Collection $rows) => $rows->isNotEmpty())
            ->each(function (Collection $rows, string $name): void {
                $rows->each(fn ($row) => $this->line($name.': '.json_encode((array) $row, JSON_UNESCAPED_UNICODE)));
            });

        $failed = collect($details)->contains(fn (Collection $rows) => $rows->isNotEmpty());

        if ($failed) {
            $this->error('User access audit found violations.');

            return self::FAILURE;
        }

        $this->info('User access audit passed.');

        return self::SUCCESS;
    }

    private function demoAccessViolations(): Collection
    {
        $expected = (new DemoAccessActorSynchronizer())->expectedActors()
            ->reject(fn (array $actor) => $actor['role'] === User::ROLE_SUPER_ADMIN)
            ->keyBy('email');

        $emails = $expected->keys()->all();
        $libraryCodes = $expected->pluck('library_code')->filter()->unique()->all();

        if (
            ! User::query()->whereIn('email', $emails)->exists()
            && ! DB::table('libraries')->whereIn('code', $libraryCodes)->exists()
        ) {
            return collect();
        }

        return $expected
            ->map(function (array $actor, string $email) {
                $role = $actor['role'];
                $libraryCode = $actor['library_code'];
                $branchCode = $actor['branch_code'] ?? null;
                $user = User::query()->where('email', $email)->first();
                $libraryId = DB::table('libraries')->where('code', $libraryCode)->value('id');

                if (! $user || ! $libraryId) {
                    return (object) ['email' => $email, 'reason' => 'missing_user_or_library'];
                }

                $membership = LibraryMembership::query()
                    ->where('user_id', $user->id)
                    ->where('library_id', $libraryId)
                    ->first();
                $expectedBranchId = $branchCode
                    ? DB::table('branches')->where('library_id', $libraryId)->where('code', $branchCode)->value('id')
                    : null;

                if (
                    $user->role !== $role
                    || ! $user->is_active
                    || ! $membership
                    || ! $membership->is_active
                    || ($role === User::ROLE_STAFF && (int) $membership->branch_id !== (int) $expectedBranchId)
                    || ($role !== User::ROLE_STAFF && $membership?->branch_id !== null)
                ) {
                    return (object) [
                        'user_id' => $user->id,
                        'email' => $email,
                        'library_id' => $libraryId,
                        'role' => $user->role,
                        'membership_active' => (bool) $membership?->is_active,
                        'branch_id' => $membership?->branch_id,
                        'expected_branch_id' => $expectedBranchId,
                    ];
                }

                return null;
            })
            ->filter()
            ->values();
    }

    private function demoLibrariesWithoutAdmin(): Collection
    {
        $libraryCodes = collect(config('demo.libraries', []))->keys();

        return DB::table('libraries')
            ->select('libraries.id', 'libraries.code')
            ->whereIn('libraries.code', $libraryCodes->all())
            ->where('libraries.is_active', true)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('library_memberships')
                    ->join('users', 'users.id', '=', 'library_memberships.user_id')
                    ->whereColumn('library_memberships.library_id', 'libraries.id')
                    ->where('library_memberships.is_active', true)
                    ->where('users.is_active', true)
                    ->where('users.role', User::ROLE_ADMIN)
                    ->whereNull('library_memberships.branch_id');
            })
            ->get();
    }

    private function activeBranchesWithoutStaff(): Collection
    {
        return DB::table('branches')
            ->select('branches.id', 'branches.library_id', 'branches.code', 'branches.name')
            ->join('libraries', 'libraries.id', '=', 'branches.library_id')
            ->where('libraries.is_active', true)
            ->when(
                Schema::hasColumn('branches', 'is_active'),
                fn ($query) => $query->where('branches.is_active', true)
            )
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('library_memberships')
                    ->join('users', 'users.id', '=', 'library_memberships.user_id')
                    ->whereColumn('library_memberships.library_id', 'branches.library_id')
                    ->whereColumn('library_memberships.branch_id', 'branches.id')
                    ->where('library_memberships.is_active', true)
                    ->where('users.is_active', true)
                    ->where('users.role', User::ROLE_STAFF);
            })
            ->get();
    }

    private function staleDemoMemberships(): Collection
    {
        $expected = (new DemoAccessActorSynchronizer())->expectedActors()
            ->reject(fn (array $actor) => $actor['role'] === User::ROLE_SUPER_ADMIN)
            ->keyBy('email');

        if ($expected->isEmpty()) {
            return collect();
        }

        return DB::table('library_memberships')
            ->select('library_memberships.id', 'users.email', 'libraries.code as library_code')
            ->join('users', 'users.id', '=', 'library_memberships.user_id')
            ->join('libraries', 'libraries.id', '=', 'library_memberships.library_id')
            ->whereIn('users.email', $expected->keys()->all())
            ->where('library_memberships.is_active', true)
            ->get()
            ->filter(fn ($membership) => ($expected[$membership->email]['library_code'] ?? null) !== $membership->library_code)
            ->values();
    }

    private function activeStaffWithInactiveBranchViolations(): Collection
    {
        if (! Schema::hasColumn('branches', 'is_active')) {
            return collect();
        }

        return DB::table('library_memberships')
            ->select('library_memberships.id', 'library_memberships.user_id', 'library_memberships.library_id', 'library_memberships.branch_id')
            ->join('users', 'users.id', '=', 'library_memberships.user_id')
            ->join('branches', 'branches.id', '=', 'library_memberships.branch_id')
            ->where('users.role', User::ROLE_STAFF)
            ->where('users.is_active', true)
            ->where('library_memberships.is_active', true)
            ->where('branches.is_active', false)
            ->get();
    }

    private function inactiveMembershipsWithEffectiveRole(): Collection
    {
        return LibraryMembership::query()
            ->with('user:id,email,role,is_active')
            ->where('is_active', false)
            ->get()
            ->filter(fn (LibraryMembership $membership) => $membership->user?->effectiveRole($membership->library_id) !== null)
            ->map(fn (LibraryMembership $membership) => (object) [
                'id' => $membership->id,
                'user_id' => $membership->user_id,
                'library_id' => $membership->library_id,
                'email' => $membership->user?->email,
                'effective_role' => $membership->user?->effectiveRole($membership->library_id),
            ])
            ->values();
    }

    private function inactiveUsersWithEffectiveRole(): Collection
    {
        return User::query()
            ->select('id', 'email', 'role', 'is_active')
            ->where('is_active', false)
            ->with('libraryMemberships:id,user_id,library_id,is_active')
            ->get()
            ->flatMap(function (User $user) {
                $libraryIds = $user->libraryMemberships
                    ->pluck('library_id')
                    ->push(null)
                    ->unique();

                return $libraryIds
                    ->map(function ($libraryId) use ($user) {
                        $effectiveRole = $user->effectiveRole($libraryId);

                        return $effectiveRole === null ? null : (object) [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'library_id' => $libraryId,
                            'effective_role' => $effectiveRole,
                        ];
                    })
                    ->filter();
            })
            ->values();
    }

    private function formatDetails(Collection $rows): string
    {
        return $rows
            ->take(5)
            ->map(fn ($row) => json_encode((array) $row, JSON_UNESCAPED_UNICODE))
            ->implode("\n");
    }
}
