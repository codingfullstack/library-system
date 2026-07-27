<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Library;
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

        $token = $user->createToken('android-app')->plainTextToken;

        return response()->json([
            'message' => 'Prisijungta sėkmingai.',
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Atsijungta sėkmingai.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $libraryId = $user->activeLibraryId();
        $library = $libraryId
            ? Library::query()->whereKey($libraryId)->first(['id', 'name'])
            : null;
        $branchId = $libraryId ? $user->assignedBranchId($libraryId) : null;
        $branch = $branchId
            ? Branch::query()->whereKey($branchId)->first(['id', 'name'])
            : null;

        return [
            'id' => $user->id,
            'library_id' => $library?->id,
            'library_name' => $library?->name,
            'branch_id' => $branch?->id,
            'branch_name' => $branch?->name,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->effectiveRole($libraryId),
            'global_role' => $user->isSuperAdmin() ? User::ROLE_SUPER_ADMIN : null,
            'membership_role' => $libraryId ? $user->effectiveRole($libraryId) : null,
            'phone' => $user->phone,
            'membership_number' => $user->membership_number,
            'is_active' => (bool) $user->is_active,
        ];
    }
}








