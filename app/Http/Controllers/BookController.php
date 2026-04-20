<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Queries\Books\GetLibraryBooksQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Queries\Books\GetLibraryBookDetailsQuery;

class BookController extends Controller
{
    public function index(Request $request, GetLibraryBooksQuery $getLibraryBooksQuery): View
    {
        $books = $getLibraryBooksQuery->handle($request->user(), [
            'search' => $request->query('search'),
            'category_id' => $request->query('category_id'),
            'sort' => $request->query('sort', 'title'),
            'direction' => $request->query('direction', 'asc'),
            'per_page' => $request->query('per_page', 15),
        ]);

        return view('books.index', [
            'books' => $books,
        ]);
    }

   public function show(Request $request, Book $book, GetLibraryBookDetailsQuery $getLibraryBookDetailsQuery): View
{
    $book = $getLibraryBookDetailsQuery->handle($request->user(), $book);

    return view('books.show', [
        'book' => $book,
    ]);
}
}