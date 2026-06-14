<?php

namespace App\Http\Controllers;

use App\Actions\Loans\BorrowBookCopyAction;
use App\Actions\Loans\ReturnBookCopyAction;
use App\Http\Requests\BorrowBookCopyRequest;
use App\Http\Resources\LibraryMemberResource;
use App\Models\BookCopy;
use App\Queries\Loans\GetMemberLoansQuery;
use App\Queries\Loans\GetActiveLibraryLoansQuery;
use App\Queries\Loans\GetLoanIndexFiltersDataQuery;
use App\Queries\Users\SearchLibraryMembersQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LoanController extends Controller
{
    public function index(
        Request $request,
        GetMemberLoansQuery $getMemberLoansQuery,
        GetActiveLibraryLoansQuery $getActiveLibraryLoansQuery,
        GetLoanIndexFiltersDataQuery $getLoanIndexFiltersDataQuery
    ): View
    {
        if ($request->user()->effectiveRole() === 'narys') {
            $filters = [
                'search' => $request->query('search'),
                'status' => $request->query('status'),
                'per_page' => $request->query('per_page', 15),
            ];

            return view('account.loans.index', [
                'loans' => $getMemberLoansQuery->handle($request->user(), $filters),
                'summary' => $getMemberLoansQuery->summary($request->user(), $filters),
            ]);
        }

        $selectedLibraryId = $request->user()->isSuperAdmin()
            ? (int) $request->query('library_id')
            : $request->user()->activeLibraryId();

        $loans = $getActiveLibraryLoansQuery->handle($request->user(), [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'member_id' => $request->query('member_id'),
            'employee_id' => $request->query('employee_id'),
            'overdue' => $request->query('overdue'),
            'due_date' => $request->query('due_date'),
            'library_id' => $request->query('library_id'),
            'per_page' => $request->query('per_page', 10),
        ]);

        $summary = $getActiveLibraryLoansQuery->summary($request->user(), [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'member_id' => $request->query('member_id'),
            'employee_id' => $request->query('employee_id'),
            'overdue' => $request->query('overdue'),
            'due_date' => $request->query('due_date'),
            'library_id' => $request->query('library_id'),
        ]);

        return view('loans.index', array_merge(
            ['loans' => $loans, 'summary' => $summary],
            $getLoanIndexFiltersDataQuery->handle($request->user(), $selectedLibraryId)
        ));
    }

    public function searchMembers(Request $request, SearchLibraryMembersQuery $searchLibraryMembersQuery): JsonResponse
    {
        $members = $searchLibraryMembersQuery->handle(
            $request->user(),
            (string) $request->query('q', '')
        );

        return response()->json(
            LibraryMemberResource::collection($members)->resolve()
        );
    }

    public function borrow(
        BorrowBookCopyRequest $request,
        BookCopy $bookCopy,
        BorrowBookCopyAction $borrowBookCopyAction
    ): RedirectResponse {
        Gate::authorize('borrow', $bookCopy);

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
        $this->authorize('return', $bookCopy);

        $returnBookCopyAction->handle(
            $request->user(),
            $bookCopy
        );

        return back()->with('success', 'Kopija sėkmingai grąžinta.');
    }
}









