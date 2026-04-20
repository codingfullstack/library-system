<?php

namespace App\Http\Controllers\Api;

use App\Actions\Loans\BorrowBookCopyAction;
use App\Actions\Loans\ReturnBookCopyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\BorrowBookCopyRequest;
use App\Models\BookCopy;
use App\Queries\Loans\GetActiveLibraryLoansQuery;
use App\Queries\Users\SearchLibraryMembersQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LoanController extends Controller
{
    public function index(Request $request, GetActiveLibraryLoansQuery $getActiveLibraryLoansQuery): JsonResponse
    {
        $loans = $getActiveLibraryLoansQuery->handle($request->user(), [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page', 1000),
        ]);

        return response()->json($loans->items());
    }

    public function searchMembers(Request $request, SearchLibraryMembersQuery $searchLibraryMembersQuery): JsonResponse
    {
        $members = $searchLibraryMembersQuery->handle(
            $request->user(),
            (string) $request->query('q', '')
        );

        return response()->json($members);
    }

    public function borrow(BorrowBookCopyRequest $request, BookCopy $bookCopy, BorrowBookCopyAction $borrowBookCopyAction): JsonResponse
    {
        \Log::info('API borrow hit', [
    'user_id' => $request->user()?->id,
    'user_role' => $request->user()?->role,
    'user_library_id' => $request->user()?->library_id,
    'book_copy_id' => $bookCopy->id,
    'book_copy_library_id' => $bookCopy->library_id,
    'status' => $bookCopy->status,
]);
        // $this->authorize('update', $bookCopy);

        $result = $borrowBookCopyAction->handle(
            $request->user(),
            $bookCopy,
            $request->validated()
        );

        return response()->json($result);
    }

    public function returnBook(Request $request, BookCopy $bookCopy, ReturnBookCopyAction $returnBookCopyAction): JsonResponse {
        $this->authorize('update', $bookCopy);

        $result = $returnBookCopyAction->handle(
            $request->user(),
            $bookCopy
        );

        return response()->json($result);
    }
}