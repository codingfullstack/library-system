<?php

namespace App\Queries\Loans;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class GetActiveLibraryLoansQuery
{
    public function handle(User $user, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        $query = Loan::query()
            ->where('library_id', $user->library_id)
            ->with([
                'user:id,name,email,membership_number',
                'bookCopy:id,book_id,inventory_code,status,branch_id,location_id',
                'bookCopy.book:id,title,subtitle,isbn',
                'bookCopy.branch:id,name',
                'bookCopy.location:id,name,room,shelf',
            ]);

        if (!empty($status)) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', ['active', 'overdue']);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('membership_number', 'like', "%{$search}%");
                })->orWhereHas('bookCopy.book', function ($bookQuery) use ($search) {
                    $bookQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%");
                })->orWhereHas('bookCopy', function ($copyQuery) use ($search) {
                    $copyQuery->where('inventory_code', 'like', "%{$search}%");
                });
            });
        }

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
}