<?php

namespace App\Queries\Books;

use App\Models\Author;
use App\Models\Category;
use App\Models\Library;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Support\Collection;

class GetBookIndexFiltersDataQuery
{
    /**
     * @return array{categories: Collection<int, Category>, authors: Collection<int, Author>, publishers: Collection<int, Publisher>, libraries: Collection<int, Library>}
     */
    public function handle(User $user): array
    {
        return [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'authors' => Author::query()->orderBy('name')->get(['id', 'name']),
            'publishers' => Publisher::query()->orderBy('name')->get(['id', 'name']),
            'libraries' => $user->isSuperAdmin()
                ? Library::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ];
    }
}
