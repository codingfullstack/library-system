<?php

namespace App\Queries\Management\Authors;

use App\Models\Author;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetManageAuthorsQuery
{
    public function handle(string $search = ''): LengthAwarePaginator
    {
        return Author::query()
            ->withCount('books')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('bio', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
    }
}
