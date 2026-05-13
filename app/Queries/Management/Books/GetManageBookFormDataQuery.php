<?php

namespace App\Queries\Management\Books;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;

class GetManageBookFormDataQuery
{
    public function handle(Book $book): array
    {
        return [
            'book' => $book,
            'authors' => Author::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'publishers' => Publisher::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}








