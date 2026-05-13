<?php

namespace App\Queries\Management\Categories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetManageCategoriesQuery
{
    public function handle(string|array $search = ''): LengthAwarePaginator
    {
        $filters = is_array($search) ? $search : ['search' => $search];
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Category::query()
            ->withCount('books')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }
}








