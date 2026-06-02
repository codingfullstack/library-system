<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteDeviceTokenRequest;
use App\Http\Requests\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $deviceToken = DeviceToken::query()->updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->getKey(),
                'device_name' => $validated['device_name'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Ireginio token issaugotas.',
            'device_token' => [
                'id' => $deviceToken->id,
                'device_name' => $deviceToken->device_name,
            ],
        ]);
    }

    public function destroy(DeleteDeviceTokenRequest $request): JsonResponse
    {
        $deleted = $request->user()
            ->deviceTokens()
            ->where('token', $request->validated('token'))
            ->delete();

        return response()->json([
            'message' => $deleted > 0
                ? 'Ireginio token pasalintas.'
                : 'Ireginio token nerastas.',
        ]);
    }
}
