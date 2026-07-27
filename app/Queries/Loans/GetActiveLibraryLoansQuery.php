<?php

namespace App\Queries\Loans;

use App\Models\Loan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class GetActiveLibraryLoansQuery
{
    public function handle(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 25), 100));

        $query = $this->baseQuery($user, $filters)
            ->with([
                'user:id,name,email,membership_number',
                'issuer:id,name',
                'receiver:id,name',
                'bookCopy:id,book_id,inventory_code,status,branch_id,location_id',
                'bookCopy.book:id,slug,title,subtitle,isbn',
                'bookCopy.branch:id,name',
                'bookCopy.location:id,name,room,shelf',
            ]);

        return $query
            ->orderBy('due_at')
            ->paginate($perPage, [
                'id',
                'library_id',
                'book_copy_id',
                'user_id',
                'issued_by',
                'received_by',
                'borrowed_at',
                'due_at',
                'returned_at',
                'status',
                'renewal_count',
                'notes',
            ])
            ->withQueryString();
    }

    public function summary(User $user, array $filters = []): array
    {
        $base = $this->baseQuery($user, $filters);

        return [
            'active_loans_count' => (clone $base)->active()->count(),
            'unique_members_count' => (clone $base)->distinct('user_id')->count('user_id'),
            'due_today_count' => (clone $base)
                ->whereNull('returned_at')
                ->whereBetween('due_at', [today()->startOfDay(), today()->endOfDay()])
                ->count(),
            'overdue_loans_count' => (clone $base)
                ->whereNull('returned_at')
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
        ];
    }

    protected function baseQuery(User $user, array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $memberId = $filters['member_id'] ?? null;
        $employeeId = $filters['employee_id'] ?? null;
        $overdue = $filters['overdue'] ?? null;
        $libraryId = $user->isSuperAdmin() ? ($filters['library_id'] ?? null) : $user->activeLibraryId();
        $branchId = filled($filters['branch_id'] ?? null) ? (int) $filters['branch_id'] : null;
        $dueDate = $filters['due_date'] ?? null;

        $query = Loan::query()
            ->when(! empty($libraryId), fn ($builder) => $builder->where('library_id', $libraryId));

        if ($user->effectiveRole($libraryId) === User::ROLE_STAFF) {
            $branchId = $user->assignedBranchId($libraryId);

            $query->whereHas('bookCopy', fn ($copyQuery) => $branchId
                ? $copyQuery->where('branch_id', $branchId)
                : $copyQuery->whereRaw('1 = 0'));
        }

        if ($branchId && $user->effectiveRole($libraryId) !== User::ROLE_STAFF) {
            $query->whereHas('bookCopy', fn ($copyQuery) => $copyQuery->where('branch_id', $branchId));
        }

        if (! empty($status)) {
            $query->where('status', $status);
        } else {
            $query->active();
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('membership_number', 'like', "%{$search}%");
                })->orWhereHas('issuer', function ($issuerQuery) use ($search) {
                    $issuerQuery->where('name', 'like', "%{$search}%");
                })->orWhereHas('receiver', function ($receiverQuery) use ($search) {
                    $receiverQuery->where('name', 'like', "%{$search}%");
                })->orWhereHas('bookCopy.book', function ($bookQuery) use ($search) {
                    $bookQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%");
                })->orWhereHas('bookCopy', function ($copyQuery) use ($search) {
                    $copyQuery->where('inventory_code', 'like', "%{$search}%");
                });
            });
        }

        if (! empty($memberId)) {
            $query->where('user_id', $memberId);
        }

        if (! empty($employeeId)) {
            $query->where(function ($employeeQuery) use ($employeeId) {
                $employeeQuery->where('issued_by', $employeeId)
                    ->orWhere('received_by', $employeeId);
            });
        }

        if (! empty($dueDate)) {
            $date = CarbonImmutable::parse($dueDate);

            $query->whereBetween('due_at', [$date->startOfDay(), $date->endOfDay()]);
        }

        if ($overdue === 'yes') {
            $query->whereNull('returned_at')
                ->whereNotNull('due_at')
                ->where('due_at', '<', now());
        }

        if ($overdue === 'no') {
            $query->where(function ($overdueQuery) {
                $overdueQuery->whereNotNull('returned_at')
                    ->orWhereNull('due_at')
                    ->orWhere('due_at', '>=', now());
            });
        }

        return $query;
    }
}
