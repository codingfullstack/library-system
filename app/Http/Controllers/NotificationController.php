<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserNotificationResource;
use App\Queries\Notifications\GetUserNotificationsQuery;
use App\Support\Notifications\NotificationUiConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $infoTypes = NotificationUiConfig::typesForCategory('info');
        $loanTypes = NotificationUiConfig::typesForCategory('loan');
        $warningTypes = NotificationUiConfig::typesForCategory('warning');
        $reservationTypes = NotificationUiConfig::typesForCategory('reservation');

        $infoCount = (clone $allNotifications)->whereIn('type', $infoTypes)->count();
        $loanCount = (clone $allNotifications)->whereIn('type', $loanTypes)->count();
        $warningCount = (clone $allNotifications)->whereIn('type', $warningTypes)->count();
        $reservationCount = (clone $allNotifications)->whereIn('type', $reservationTypes)->count();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'loanCount' => $loanCount,
            'reservationCount' => $reservationCount,
            'infoCount' => $infoCount,
            'warningCount' => $warningCount,
            'filters' => $filters,
        ]);
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()
            ->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Pranešimai pažymėti kaip perskaityti.',
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ]);
        }

        return redirect()
            ->route('notifications.index', $request->only(['category', 'status', 'date', 'sort', 'per_page']))
            ->with('status', 'Visi pranešimai pažymėti kaip perskaityti.');
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
            'message' => 'Pranešimas pažymėtas kaip perskaitytas.',
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }
}
