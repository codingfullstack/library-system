<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Support\UserManagement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicLibraryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->role === 'narys', 403);

        $memberLibraryIds = $user->activeLibraryMemberships()
            ->pluck('library_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $libraries = Library::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'address', 'city'])
            ->map(fn (Library $library) => $this->libraryPayload($library, in_array($library->id, $memberLibraryIds, true)))
            ->values();

        return response()->json([
            'libraries' => $libraries,
        ]);
    }

    public function join(Request $request, Library $library): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->role === 'narys', 403);
        abort_unless($library->is_active && $library->is_public, 404);

        if (blank($user->membership_number)) {
            $user->forceFill([
                'membership_number' => UserManagement::generateMembershipNumber(),
            ])->save();
        }

        LibraryMembership::query()->updateOrCreate(
            [
                'library_id' => $library->id,
                'user_id' => $user->id,
            ],
            [
                'membership_number' => $user->membership_number ?: UserManagement::generateMembershipNumber(),
                'is_active' => true,
                'joined_at' => now(),
            ]
        );

        return response()->json([
            'message' => sprintf('Prisijungėte prie bibliotekos "%s".', $library->name),
            'library' => $this->libraryPayload($library, true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function libraryPayload(Library $library, bool $isMember): array
    {
        return [
            'id' => $library->id,
            'name' => $library->name,
            'code' => $library->code,
            'address' => $library->address,
            'city' => $library->city,
            'is_member' => $isMember,
        ];
    }
}
