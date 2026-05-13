<?php

namespace App\Queries\Management\Publishers;

use App\Models\Publisher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetManagePublishersQuery
{
    public function handle(string|array $search = ''): LengthAwarePaginator
    {
        $filters = is_array($search) ? $search : ['search' => $search];
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Publisher::query()
            ->withCount('books')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }
}








