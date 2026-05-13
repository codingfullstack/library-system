<?php

namespace App\Http\Controllers\Api;

use App\Actions\Loans\BorrowBookCopyAction;
use App\Actions\Loans\ReturnBookCopyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\BorrowBookCopyRequest;
use App\Http\Resources\LibraryMemberResource;
use App\Http\Resources\LoanResource;
use App\Models\BookCopy;
use App\Queries\Loans\GetActiveLibraryLoansQuery;
use App\Queries\Loans\GetMemberLoansQuery;
use App\Queries\Users\SearchLibraryMembersQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function index(
        Request $request,
        GetActiveLibraryLoansQuery $getActiveLibraryLoansQuery,
        GetMemberLoansQuery $getMemberLoansQuery
    ): JsonResponse
    {
        $user = $request->user();
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'member_id' => $request->query('member_id'),
            'employee_id' => $request->query('employee_id'),
            'overdue' => $request->query('overdue'),
            'library_id' => $request->query('library_id'),
            'per_page' => $request->query('per_page', 1000),
        ];

        $loans = $user?->role === 'narys'
            ? $getMemberLoansQuery->handle($user, $filters)
            : $getActiveLibraryLoansQuery->handle($user, $filters);

        return response()->json(
            LoanResource::collection(collect($loans->items()))->resolve()
        );
    }

    public function searchMembers(Request $request, SearchLibraryMembersQuery $searchLibraryMembersQuery): JsonResponse
    {
        abort_if($request->user()?->role === 'narys', 403);

        $members = $searchLibraryMembersQuery->handle(
            $request->user(),
            (string) $request->query('q', '')
        );

        return response()->json(
            LibraryMemberResource::collection($members)->resolve()
        );
    }

    public function borrow(BorrowBookCopyRequest $request, BookCopy $bookCopy, BorrowBookCopyAction $borrowBookCopyAction): JsonResponse
    {
        $this->authorize('update', $bookCopy);

        $result = $borrowBookCopyAction->handle(
            $request->user(),
            $bookCopy,
            $request->validated()
        );

        return response()->json($result);
    }

    public function returnBook(Request $request, BookCopy $bookCopy, ReturnBookCopyAction $returnBookCopyAction): JsonResponse
    {
        $this->authorize('update', $bookCopy);

        $result = $returnBookCopyAction->handle(
            $request->user(),
            $bookCopy
        );

        return response()->json($result);
    }
}









