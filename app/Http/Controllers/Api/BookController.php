<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookDetailsResource;
use App\Http\Resources\BookResource;
use App\Queries\Books\GetLibraryBookDetailsQuery;
use App\Queries\Books\GetLibraryBooksQuery;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index(Request $request, GetLibraryBooksQuery $getLibraryBooksQuery): JsonResponse
    {
        $books = $getLibraryBooksQuery->handle($request->user(), [
            'search' => $request->query('search'),
            'category_id' => $request->query('category_id'),
            'author_id' => $request->query('author_id'),
            'publisher_id' => $request->query('publisher_id'),
            'library_id' => $request->query('library_id'),
            'availability' => $request->query('availability'),
            'sort' => $request->query('sort', 'title'),
            'direction' => $request->query('direction', 'asc'),
            'per_page' => $request->query('per_page', 1000),
        ]);

        return response()->json(
            BookResource::collection(collect($books->items()))->resolve()
        );
    }

    public function show(
        Request $request,
        Book $book,
        GetLibraryBookDetailsQuery $getLibraryBookDetailsQuery
    ): JsonResponse {
        $book = $getLibraryBookDetailsQuery->handle($request->user(), $book, [
            'copy_status' => $request->query('copy_status'),
            'branch_id' => $request->query('branch_id'),
            'location_id' => $request->query('location_id'),
        ]);

        return response()->json(
            (new BookDetailsResource($book))->resolve()
        );
    }
}








