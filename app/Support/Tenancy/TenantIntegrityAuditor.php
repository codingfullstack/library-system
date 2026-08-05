<?php

namespace App\Support\Tenancy;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantIntegrityAuditor
{
    /**
     * @return Collection<int, TenantIntegrityViolation>
     */
    public function violations(): Collection
    {
        return collect()
            ->merge($this->tenantMismatch('locations', 'branch_id', 'branches', 'branch_tenant_mismatch'))
            ->merge($this->tenantMismatch('book_copies', 'branch_id', 'branches', 'branch_tenant_mismatch'))
            ->merge($this->tenantMismatch('book_copies', 'location_id', 'locations', 'location_tenant_mismatch', nullable: true))
            ->merge($this->tenantMismatch('library_memberships', 'branch_id', 'branches', 'branch_tenant_mismatch', nullable: true))
            ->merge($this->tenantMismatch('loans', 'book_copy_id', 'book_copies', 'book_copy_tenant_mismatch'))
            ->merge($this->membershipMissing('loans', 'user_id', 'borrower_membership_missing'))
            ->merge($this->membershipMissing('reservations', 'user_id', 'member_membership_missing'))
            ->merge($this->tenantMismatch('reservations', 'branch_id', 'branches', 'branch_tenant_mismatch', nullable: true))
            ->merge($this->tenantMismatch('reservations', 'pickup_branch_id', 'branches', 'pickup_branch_tenant_mismatch', nullable: true))
            ->merge($this->tenantMismatch('reservations', 'assigned_book_copy_id', 'book_copies', 'assigned_book_copy_tenant_mismatch', nullable: true))
            ->merge($this->tenantMismatch('scan_logs', 'book_copy_id', 'book_copies', 'book_copy_tenant_mismatch', nullable: true))
            ->values();
    }

    public function hasViolations(): bool
    {
        return $this->violations()->isNotEmpty();
    }

    /**
     * @return Collection<int, TenantIntegrityViolation>
     */
    private function tenantMismatch(
        string $table,
        string $foreignKey,
        string $relatedTable,
        string $type,
        bool $nullable = false
    ): Collection {
        $query = DB::table($table)
            ->join($relatedTable, "{$relatedTable}.id", '=', "{$table}.{$foreignKey}")
            ->whereColumn("{$relatedTable}.library_id", '!=', "{$table}.library_id")
            ->select([
                "{$table}.id",
                "{$table}.library_id",
                "{$table}.{$foreignKey} as related_id",
            ]);

        if ($nullable) {
            $query->whereNotNull("{$table}.{$foreignKey}");
        }

        return $query
            ->orderBy("{$table}.id")
            ->get()
            ->map(fn ($row) => new TenantIntegrityViolation(
                table: $table,
                id: (int) $row->id,
                type: $type,
                libraryId: (int) $row->library_id,
                relatedTable: $relatedTable,
                relatedId: (int) $row->related_id,
            ));
    }

    /**
     * @return Collection<int, TenantIntegrityViolation>
     */
    private function membershipMissing(
        string $table,
        string $userKey,
        string $type,
        bool $nullable = false
    ): Collection {
        $query = DB::table($table)
            ->leftJoin('library_memberships', function ($join) use ($table, $userKey) {
                $join->on('library_memberships.library_id', '=', "{$table}.library_id")
                    ->on('library_memberships.user_id', '=', "{$table}.{$userKey}");
            })
            ->whereNull('library_memberships.id')
            ->select([
                "{$table}.id",
                "{$table}.library_id",
                "{$table}.{$userKey} as related_id",
            ]);

        if ($nullable) {
            $query->whereNotNull("{$table}.{$userKey}");
        }

        return $query
            ->orderBy("{$table}.id")
            ->get()
            ->map(fn ($row) => new TenantIntegrityViolation(
                table: $table,
                id: (int) $row->id,
                type: $type,
                libraryId: (int) $row->library_id,
                relatedTable: 'library_memberships',
                relatedId: (int) $row->related_id,
            ));
    }
}
