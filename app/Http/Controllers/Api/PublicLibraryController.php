<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Library;
use App\Support\LibraryJoinService;
use App\Support\UserManagement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicLibraryController extends Controller
{
    public function index(Request $request, LibraryJoinService $libraryJoinService): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->role === 'narys', 403);

        $libraries = Library::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'address', 'city'])
            ->map(fn (Library $library) => $this->libraryPayload(
                $library,
                $libraryJoinService->statusFor($user, $library)
            ))
            ->values();

        return response()->json([
            'libraries' => $libraries,
        ]);
    }

    public function join(Request $request, Library $library, LibraryJoinService $libraryJoinService): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->role === 'narys', 403);
        abort_unless($library->is_active && $library->is_public, 404);

        if (blank($user->membership_number)) {
            $user->forceFill([
                'membership_number' => UserManagement::generateMembershipNumber(),
            ])->save();
        }

        $result = $libraryJoinService->join($user, $library);

        if ($result->isInactive()) {
            return response()->json([
                'message' => LibraryJoinService::INACTIVE_MESSAGE,
            ], 403);
        }

        return response()->json([
            'message' => sprintf('Prisijungėte prie bibliotekos "%s".', $library->name),
            'library' => $this->libraryPayload($library, [
                'membership_status' => LibraryJoinService::STATUS_ACTIVE,
                'can_join' => false,
            ]),
        ]);
    }

    /**
     * @param  array{membership_status: string, can_join: bool}  $membershipState
     * @return array<string, mixed>
     */
    private function libraryPayload(Library $library, array $membershipState): array
    {
        return [
            'id' => $library->id,
            'name' => $library->name,
            'code' => $library->code,
            'address' => $library->address,
            'city' => $library->city,
            'is_member' => $membershipState['membership_status'] === LibraryJoinService::STATUS_ACTIVE,
            'membership_status' => $membershipState['membership_status'],
            'can_join' => $membershipState['can_join'],
        ];
    }
}
