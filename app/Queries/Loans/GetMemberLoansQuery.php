<?php

namespace App\Queries\Loans;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetMemberLoansQuery
{
    public function handle(User $user, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        $query = Loan::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'overdue'])
            ->whereNull('returned_at')
            ->with([
                'bookCopy:id,book_id,inventory_code',
                'bookCopy.book:id,title,subtitle,isbn',
                'issuer:id,name',
            ]);

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

        if ($status === 'active') {
            $query->where('status', 'active');
        }

        if ($status === 'overdue') {
            $query->where('status', 'overdue');
        }

        return $query
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
