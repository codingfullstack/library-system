<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookDetailsResource;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Models\BookCopy;
use App\Queries\Books\GetLibraryBookDetailsQuery;
use App\Queries\Books\GetLibraryBooksQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function index(Request $request, GetLibraryBooksQuery $getLibraryBooksQuery): JsonResponse
    {
        $this->ensureCatalogContext($request);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'author_id' => ['nullable', 'integer', 'exists:authors,id'],
            'publisher_id' => ['nullable', 'integer', 'exists:publishers,id'],
            'library_id' => ['nullable', 'integer', 'exists:libraries,id'],
            'availability' => ['nullable', Rule::in(['laisva', 'unavailable'])],
            'sort' => ['nullable', Rule::in(['title', 'publication_year', 'copies_count', 'created_at', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'scope_to_assigned_branch' => ['nullable', 'boolean'],
        ]);

        $filters = [
            'search' => $validated['search'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'author_id' => $validated['author_id'] ?? null,
            'publisher_id' => $validated['publisher_id'] ?? null,
            'library_id' => $validated['library_id'] ?? null,
            'availability' => $validated['availability'] ?? null,
            'sort' => $validated['sort'] ?? 'title',
            'direction' => $validated['direction'] ?? 'asc',
            'per_page' => $validated['per_page'] ?? 25,
            'scope_to_assigned_branch' => $request->boolean('scope_to_assigned_branch'),
            'active_library_only' => true,
        ];

        $books = $getLibraryBooksQuery->handle($request->user(), $filters);
        $summary = $getLibraryBooksQuery->summary($request->user(), $filters);
        $response = BookResource::collection($books)->response($request)->getData(true);
        $response['meta']['summary'] = $summary;

        return response()->json($response);
    }

    public function show(
        Request $request,
        Book $book,
        GetLibraryBookDetailsQuery $getLibraryBookDetailsQuery
    ): JsonResponse {
        $this->ensureCatalogContext($request);

        $validated = $request->validate([
            'copy_status' => ['nullable', Rule::in(array_keys(BookCopy::statusLabels()))],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        $book = $getLibraryBookDetailsQuery->handle($request->user(), $book, [
            'copy_status' => $validated['copy_status'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'active_library_only' => true,
        ]);

        return response()->json(
            (new BookDetailsResource($book))->resolve()
        );
    }

    private function ensureCatalogContext(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && ($user->isSuperAdmin() || $user->activeLibraryId()), 403, 'Neturite aktyvios narystes bibliotekos katalogui.');
    }
}
