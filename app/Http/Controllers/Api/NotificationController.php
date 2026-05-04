<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserNotificationResource;
use App\Queries\Notifications\GetUserNotificationsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'unread_count' => $user->notifications()->whereNull('read_at')->count(),
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
        ]);
    }
}
