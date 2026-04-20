<?php

namespace App\Http\Controllers;

use App\Actions\Loans\BorrowBookCopyAction;
use App\Actions\Loans\ReturnBookCopyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\BorrowBookCopyRequest;
use App\Models\BookCopy;
use App\Queries\Loans\GetActiveLibraryLoansQuery;
use App\Queries\Users\SearchLibraryMembersQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\JsonResponse;

class LoanController extends Controller
{
    public function index(Request $request, GetActiveLibraryLoansQuery $getActiveLibraryLoansQuery): View
    {
        $loans = $getActiveLibraryLoansQuery->handle($request->user(), [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page', 15),
        ]);

        return view('loans.index', [
            'loans' => $loans,
        ]);
    }

    public function searchMembers(Request $request, SearchLibraryMembersQuery $searchLibraryMembersQuery): JsonResponse
    {
        $members = $searchLibraryMembersQuery->handle(
            $request->user(),
            (string) $request->query('q', '')
        );

        return response()->json($members);
    }

    public function borrow(
        BorrowBookCopyRequest $request,
        BookCopy $bookCopy,
        BorrowBookCopyAction $borrowBookCopyAction
    ): RedirectResponse {
        Gate::authorize('update', $bookCopy);

        $borrowBookCopyAction->handle(
            $request->user(),
            $bookCopy,
            $request->validated()
        );

        return back()->with('success', 'Kopija sėkmingai išduota.');
    }

    public function returnBook(
        Request $request,
        BookCopy $bookCopy,
        ReturnBookCopyAction $returnBookCopyAction
    ): RedirectResponse {
        Gate::authorize('update', $bookCopy);

        $returnBookCopyAction->handle(
            $request->user(),
            $bookCopy
        );

        return back()->with('success', 'Kopija sėkmingai grąžinta.');
    }
}