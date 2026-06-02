<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserNotificationResource;
use App\Queries\Notifications\GetUserNotificationsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request, GetUserNotificationsQuery $getUserNotificationsQuery): JsonResponse
    {
        $user = $request->user();
        $notifications = $getUserNotificationsQuery->handle(
            $user,
            (int) $request->query('per_page', 100)
        );

        return response()->json([
            'items' => UserNotificationResource::collection(collect($notifications->items()))->resolve(),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()
            ->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Pranesimai pazymeti kaip perskaityti.',
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
