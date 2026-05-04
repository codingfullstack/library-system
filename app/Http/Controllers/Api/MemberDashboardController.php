<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoanResource;
use App\Http\Resources\ReservationResource;
use App\Http\Resources\UserNotificationResource;
use App\Queries\Users\GetMemberDashboardDataQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberDashboardController extends Controller
{
    public function __invoke(Request $request, GetMemberDashboardDataQuery $getMemberDashboardDataQuery): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->role === 'member', 403);

        $dashboard = $getMemberDashboardDataQuery->handle($user);

        return response()->json([
            'active_loans_count' => $dashboard['activeLoansCount'],
            'active_reservations_count' => $dashboard['activeReservationsCount'],
            'overdue_loans_count' => $dashboard['overdueLoansCount'],
            'unread_notifications_count' => $dashboard['unreadNotificationsCount'],
            'active_loans' => LoanResource::collection($dashboard['activeLoans'])->resolve(),
            'active_reservations' => ReservationResource::collection($dashboard['activeReservations'])->resolve(),
            'recent_notifications' => UserNotificationResource::collection($dashboard['recentNotifications'])->resolve(),
        ]);
    }
}
