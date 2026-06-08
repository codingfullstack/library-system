<?php

namespace App\Queries\Management\BookCopies;

use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GetManageBookCopiesQuery
{
    public function handle(User $user, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = filled($filters['status'] ?? null) ? (string) $filters['status'] : null;
        $branchId = filled($filters['branch_id'] ?? null) ? (int) $filters['branch_id'] : null;
        $perPage = (int) ($filters['per_page'] ?? 10);

        return $this->baseQuery($user)
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('inventory_code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhereHas('book', function (Builder $bookQuery) use ($search) {
                            $bookQuery
                                ->where('title', 'like', "%{$search}%")
                                ->orWhere('isbn', 'like', "%{$search}%");
                        })
                        ->orWhereHas('branch', fn (Builder $branchQuery) => $branchQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('location', fn (Builder $locationQuery) => $locationQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->latest('updated_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function summary(User $user): array
    {
        $query = $this->baseQuery($user);

        $total = (clone $query)->count();
        $available = (clone $query)->where('status', BookCopy::STATUS_AVAILABLE)->count();
        $loaned = (clone $query)->where('status', BookCopy::STATUS_LOANED)->count();
        $unavailable = (clone $query)->whereIn('status', [
            BookCopy::STATUS_LOST,
            BookCopy::STATUS_DAMAGED,
            BookCopy::STATUS_MAINTENANCE,
            BookCopy::STATUS_WITHDRAWN,
        ])->count();

        return [
            'total' => $total,
            'laisva' => $available,
            'išduota' => $loaned,
            'unavailable' => $unavailable,
        ];
    }

    public function branches(User $user): Collection
    {
        return Branch::query()
            ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->where('library_id', $user->activeLibraryId()))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function baseQuery(User $user): Builder
    {
        return BookCopy::query()
            ->with([
                'book:id,slug,title,isbn,cover_image',
                'branch:id,name',
                'location:id,name,room,shelf',
                'library:id,name',
                'activeLoan.user:id,name,membership_number',
            ])
            ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->where('library_id', $user->activeLibraryId()));
    }
}
