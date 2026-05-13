<?php

namespace App\Http\Controllers\Api;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Notifications\CreateUserNotificationAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\LibraryMemberResource;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserMembershipScanController extends Controller
{
    public function show(Request $request, string $membershipNumber): JsonResponse
    {
        $actor = $request->user();
        abort_if($actor?->role === 'narys', 403);

        $member = User::query()
            ->where('membership_number', $membershipNumber)
            ->where('role', 'narys')
            ->first();

        if (! $member) {
            return response()->json([
                'message' => 'Vartotojas nerastas',
            ], 404);
        }

        $library = $this->workLibrary($actor);

        return response()->json([
            'member' => (new LibraryMemberResource($member))->resolve(),
            'library' => $library ? [
                'id' => $library->id,
                'name' => $library->name,
                'code' => $library->code,
            ] : null,
            'libraries' => $actor->isSuperAdmin()
                ? Library::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'code'])
                    ->map(fn (Library $library) => [
                        'id' => $library->id,
                        'name' => $library->name,
                        'code' => $library->code,
                    ])
                    ->values()
                : [],
            'already_member' => $library ? $member->belongsToLibrary($library->id) : false,
        ]);
    }

    public function store(
        Request $request,
        RecordAuditLogAction $recordAuditLogAction,
        CreateUserNotificationAction $createUserNotificationAction
    ): JsonResponse {
        $actor = $request->user();
        abort_if($actor?->role === 'narys', 403);

        $validated = $request->validate([
            'membership_number' => ['required', 'string', 'max:64', Rule::exists('users', 'membership_number')],
            'library_id' => [
                Rule::requiredIf(fn () => $actor->isSuperAdmin()),
                'nullable',
                'integer',
                'exists:libraries,id',
            ],
        ]);

        $member = User::query()
            ->where('membership_number', trim($validated['membership_number']))
            ->where('role', 'narys')
            ->firstOrFail();

        $library = $actor->isSuperAdmin() && filled($validated['library_id'] ?? null)
            ? Library::query()->whereKey($validated['library_id'])->where('is_active', true)->first()
            : $this->workLibrary($actor);
        abort_unless($library, 404);

        if ($member->belongsToLibrary($library->id)) {
            return response()->json([
                'message' => 'Vartotojas jau priklauso šiai bibliotekai.',
                'member' => (new LibraryMemberResource($member))->resolve(),
                'already_member' => true,
            ]);
        }

        $membership = LibraryMembership::query()->updateOrCreate(
            [
                'library_id' => $library->id,
                'user_id' => $member->id,
            ],
            [
                'membership_number' => $member->membership_number,
                'is_active' => true,
                'joined_at' => now(),
            ]
        );

        $recordAuditLogAction->handle(
            $actor,
            'user_membership_added_by_scan',
            $member,
            sprintf('Vartotojas "%s" pridėtas prie bibliotekos "%s" pagal QR kodą.', $member->name, $library->name),
            [
                'target_user_id' => $member->id,
                'target_user_name' => $member->name,
                'target_user_membership_number' => $member->membership_number,
                'library_id' => $library->id,
                'library_name' => $library->name,
                'membership_id' => $membership->id,
            ],
            $library->id
        );

        $createUserNotificationAction->handle(
            $member,
            $actor,
            'library_membership_added',
            'Pridėta bibliotekos narystė',
            sprintf('Jūs buvote pridėtas prie bibliotekos "%s".', $library->name),
            [
                'library_id' => $library->id,
                'library_name' => $library->name,
            ],
            LibraryMembership::class,
            $membership->id
        );

        return response()->json([
            'message' => sprintf('Vartotojas pridėtas prie bibliotekos "%s".', $library->name),
            'member' => (new LibraryMemberResource($member))->resolve(),
            'already_member' => false,
        ]);
    }

    private function workLibrary(User $actor): ?Library
    {
        if ($actor->isSuperAdmin()) {
            return null;
        }

        if (! in_array($actor->role, ['administratorius', 'darbuotojas'], true)) {
            return null;
        }

        $membership = $actor->activeLibraryMemberships()
            ->whereHas('library', fn ($query) => $query->where('is_active', true))
            ->with('library:id,name,code')
            ->orderBy('joined_at')
            ->orderBy('id')
            ->first();

        return $membership?->library;
    }
}









