<?php

namespace App\Http\Controllers;

use App\Queries\Loans\GetMemberLoansQuery;
use App\Queries\Reservations\GetMemberReservationsQuery;
use App\Queries\Users\GetMemberDashboardDataQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function dashboard(Request $request, GetMemberDashboardDataQuery $getMemberDashboardDataQuery): View
    {
        return view('account.dashboard', $getMemberDashboardDataQuery->handle($request->user()));
    }

    public function loans(Request $request, GetMemberLoansQuery $getMemberLoansQuery): View
    {
        return view('account.loans.index', [
            'loans' => $getMemberLoansQuery->handle($request->user(), [
                'search' => $request->query('search'),
                'status' => $request->query('status'),
                'per_page' => $request->query('per_page', 15),
            ]),
        ]);
    }

    public function reservations(Request $request, GetMemberReservationsQuery $getMemberReservationsQuery): View
    {
        return view('account.reservations.index', [
            'reservations' => $getMemberReservationsQuery->handle($request->user(), [
                'search' => $request->query('search'),
                'status' => $request->query('status'),
                'per_page' => $request->query('per_page', 15),
            ]),
        ]);
    }

    public function profile(Request $request): View
    {
        $user = $request->user()->load('library:id,name,email,phone,address,city');

        return view('account.profile', [
            'member' => $user,
            'library' => $user->library,
            'activeLoansCount' => $user->loans()
                ->whereIn('status', ['active', 'overdue'])
                ->whereNull('returned_at')
                ->count(),
            'activeReservationsCount' => $user->reservations()
                ->where('status', 'reserved')
                ->whereNull('fulfilled_at')
                ->whereNull('cancelled_at')
                ->count(),
        ]);
    }
}
