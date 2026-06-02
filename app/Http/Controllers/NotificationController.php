<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserNotificationResource;
use App\Queries\Notifications\GetUserNotificationsQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

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
        $reminderCount = (clone $allNotifications)->whereIn('type', ['loan_overdue', 'book_due_soon', 'reservation_ready'])->count();
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

    public function markAllRead(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $request->user()
            ->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Pranesimai pazymeti kaip perskaityti.',
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ]);
        }

        return redirect()
            ->route('notifications.index', $request->only(['category', 'status', 'date', 'sort', 'per_page']))
            ->with('status', 'Visi pranesimai pazymeti kaip perskaityti.');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->limit((int) $request->integer('limit', 8))
            ->get();

        return response()->json([
            'items' => UserNotificationResource::collection($notifications)->resolve(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        abort_unless(
            $notification->notifiable_type === $request->user()->getMorphClass()
                && (string) $notification->notifiable_id === (string) $request->user()->getKey(),
            404
        );

        $notification->markAsRead();

        return response()->json([
            'message' => 'Pranesimas pazymetas kaip perskaitytas.',
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }
}
