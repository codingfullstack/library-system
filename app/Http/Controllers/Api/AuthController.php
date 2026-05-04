<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Neteisingi prisijungimo duomenys.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Paskyra neaktyvi.',
            ], 403);
        }

        $user->loadMissing('library:id,name');
        $token = $user->createToken('android-app')->plainTextToken;

        return response()->json([
            'message' => 'Prisijungta sekmingai.',
            'token' => $token,
            'user' => array_merge($user->toArray(), [
                'library_name' => $user->library?->name,
            ]),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Atsijungta sekmingai.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('library:id,name');

        return response()->json([
            'user' => array_merge($user->toArray(), [
                'library_name' => $user->library?->name,
            ]),
        ]);
    }
}
