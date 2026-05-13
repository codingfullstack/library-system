<?php

namespace App\Queries\Loans;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetMemberLoansQuery
{
    public function handle(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return $this->baseQuery($user, $filters)
            ->with([
                'bookCopy:id,book_id,inventory_code',
                'bookCopy.book:id,title,subtitle,isbn',
                'library:id,name',
                'issuer:id,name',
            ])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array{all_count:int, active_count:int, overdue_count:int, due_soon_count:int, no_due_date_count:int}
     */
    public function summary(User $user, array $filters = []): array
    {
        $query = $this->baseQuery($user, $filters);

        return [
            'all_count' => (clone $query)->count(),
            'active_count' => (clone $query)->where('status', 'aktyvi')->count(),
            'overdue_count' => (clone $query)->where(function ($builder) {
                $builder->where('status', 'vėluoja')
                    ->orWhere('due_at', '<', now());
            })->count(),
            'due_soon_count' => (clone $query)
                ->whereNotNull('due_at')
                ->whereBetween('due_at', [now(), now()->addDays(7)])
                ->count(),
            'no_due_date_count' => (clone $query)->whereNull('due_at')->count(),
        ];
    }

    private function baseQuery(User $user, array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $libraryId = $user->activeLibraryId();

        $query = Loan::query()
            ->where('user_id', $user->id)
            ->when($libraryId, fn ($builder) => $builder->where('library_id', $libraryId))
            ->whereIn('status', ['aktyvi', 'vėluoja'])
            ->whereNull('returned_at');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->whereHas('bookCopy.book', function ($bookQuery) use ($search) {
                    $bookQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%");
                })->orWhereHas('bookCopy', function ($copyQuery) use ($search) {
                    $copyQuery->where('inventory_code', 'like', "%{$search}%");
                });
            });
        }

        if ($status === 'aktyvi') {
            $query->where('status', 'aktyvi');
        }

        if ($status === 'vėluoja') {
            $query->where('status', 'vėluoja');
        }

        return $query;
    }
}








