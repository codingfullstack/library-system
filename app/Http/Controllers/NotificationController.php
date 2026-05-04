<?php

namespace App\Http\Controllers;

use App\Queries\Notifications\GetUserNotificationsQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, GetUserNotificationsQuery $getUserNotificationsQuery): View
    {
        $user = $request->user();
        $filters = [
            'category' => (string) $request->query('category', 'all'),
            'status' => (string) $request->query('status', 'all'),
            'date' => $request->query('date'),
            'sort' => (string) $request->query('sort', 'latest'),
        ];

        $notifications = $getUserNotificationsQuery->handle(
            $user,
            (int) $request->query('per_page', 20),
            $filters
        );

        $allNotifications = $user->notifications();
        $unreadCount = (clone $allNotifications)->whereNull('read_at')->count();
        $systemCount = (clone $allNotifications)->whereIn('type', ['system', 'new_user', 'qr_scan'])->count();
        $reminderCount = (clone $allNotifications)->whereIn('type', ['loan_overdue', 'reservation_ready'])->count();
        $otherCount = max((clone $allNotifications)->count() - $systemCount - $reminderCount, 0);

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'overdueCount' => $user->notifications()->where('type', 'loan_overdue')->count(),
            'reservationCount' => $user->notifications()->where('type', 'reservation_cancelled')->count(),
            'systemCount' => $systemCount,
            'reminderCount' => $reminderCount,
            'otherCount' => $otherCount,
            'filters' => $filters,
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()
            ->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return redirect()
            ->route('notifications.index', $request->only(['category', 'status', 'date', 'sort', 'per_page']))
            ->with('status', 'Visi pranesimai pazymeti kaip perskaityti.');
    }
}
