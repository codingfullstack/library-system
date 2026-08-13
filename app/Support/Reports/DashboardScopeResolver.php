<?php

namespace App\Support\Reports;

use App\Models\Branch;
use App\Models\Library;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardScopeResolver
{
    public function resolve(User $user, mixed $requestedBranchId = null): DashboardScope
    {
        $requestedBranchId = $this->normalizeBranchId($requestedBranchId);

        if ($user->isSuperAdmin()) {
            return $this->resolveSuperAdmin($requestedBranchId);
        }

        $libraryId = $user->activeLibraryId();

        abort_unless($libraryId, 403, 'Neturite aktyvios bibliotekos konteksto.');

        $role = $user->effectiveRole($libraryId);

        abort_unless(in_array($role, [User::ROLE_ADMIN, User::ROLE_STAFF], true), 403);

        $library = Library::query()->findOrFail($libraryId, ['id', 'name', 'code']);

        if ($role === User::ROLE_STAFF) {
            $branchId = $user->assignedBranchId($libraryId);

            abort_unless($branchId, 403, 'Darbuotojas nepriskirtas galiojanciam filialui.');

            $branch = Branch::query()
                ->whereKey($branchId)
                ->where('library_id', $libraryId)
                ->first(['id', 'name']);

            abort_unless($branch, 403, 'Darbuotojas nepriskirtas galiojanciam filialui.');

            return new DashboardScope(
                libraryId: (int) $library->id,
                libraryName: $library->name,
                libraryCode: $library->code,
                branchId: (int) $branch->id,
                branchName: $branch->name,
                type: 'branch',
                canSelectBranch: false,
                branchOptions: collect([$branch]),
            );
        }

        $branches = Branch::query()
            ->where('library_id', $libraryId)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($requestedBranchId) {
            $branch = $branches->firstWhere('id', $requestedBranchId);

            abort_unless($branch, 404, 'Pasirinktas filialas nepriklauso aktyviai bibliotekai.');

            return new DashboardScope(
                libraryId: (int) $library->id,
                libraryName: $library->name,
                libraryCode: $library->code,
                branchId: (int) $branch->id,
                branchName: $branch->name,
                type: 'branch',
                canSelectBranch: true,
                branchOptions: $branches,
            );
        }

        return new DashboardScope(
            libraryId: (int) $library->id,
            libraryName: $library->name,
            libraryCode: $library->code,
            branchId: null,
            branchName: null,
            type: 'library',
            canSelectBranch: true,
            branchOptions: $branches,
        );
    }

    private function resolveSuperAdmin(?int $requestedBranchId): DashboardScope
    {
        if ($requestedBranchId) {
            $branch = Branch::query()
                ->with('library:id,name,code')
                ->findOrFail($requestedBranchId, ['id', 'library_id', 'name']);

            return new DashboardScope(
                libraryId: (int) $branch->library_id,
                libraryName: $branch->library?->name,
                libraryCode: $branch->library?->code,
                branchId: (int) $branch->id,
                branchName: $branch->name,
                type: 'branch',
                canSelectBranch: false,
                branchOptions: collect([$branch]),
            );
        }

        return new DashboardScope(
            libraryId: null,
            libraryName: null,
            libraryCode: null,
            branchId: null,
            branchName: null,
            type: 'library',
            canSelectBranch: false,
            branchOptions: new Collection,
            isGlobal: true,
        );
    }

    private function normalizeBranchId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            abort_unless($value > 0, 404);

            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            return (int) $value;
        }

        abort(404);
    }
}
