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
        $token = trim($validated['token']);

        $deviceToken = DeviceToken::query()->updateOrCreate(
            ['token_hash' => DeviceToken::hashToken($token)],
            [
                'user_id' => $request->user()->getKey(),
                'token' => $token,
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
            ->where('token_hash', DeviceToken::hashToken($request->validated('token')))
            ->delete();

        return response()->json([
            'message' => $deleted > 0
                ? 'Ireginio token pasalintas.'
                : 'Ireginio token nerastas.',
        ]);
    }
}
