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
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    public function index(
        Request $request,
        GetActiveLibraryLoansQuery $getActiveLibraryLoansQuery,
        GetMemberLoansQuery $getMemberLoansQuery
    ): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['aktyvi', 'vÄ—luoja', 'grÄ…Å¾inta', 'prarasta'])],
            'member_id' => ['nullable', 'integer', 'exists:users,id'],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'overdue' => ['nullable', Rule::in(['yes', 'no'])],
            'library_id' => ['nullable', 'integer', 'exists:libraries,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filters = [
            'search' => $validated['search'] ?? null,
            'status' => $validated['status'] ?? null,
            'member_id' => $validated['member_id'] ?? null,
            'employee_id' => $validated['employee_id'] ?? null,
            'overdue' => $validated['overdue'] ?? null,
            'library_id' => $validated['library_id'] ?? null,
            'per_page' => $validated['per_page'] ?? 25,
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
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $members = $searchLibraryMembersQuery->handle(
            $request->user(),
            (string) ($validated['q'] ?? '')
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









