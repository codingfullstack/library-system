<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\User;
use App\Support\UserManagement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserMembershipController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && ($actor->isSuperAdmin() || UserManagement::canManageRole($actor, $user->role)), 404);
        abort_unless($user->role === 'narys', 404);

        $libraryId = $actor->isSuperAdmin()
            ? $request->integer('library_id')
            : $actor->activeLibraryId();

        $request->validate([
            'library_id' => [
                Rule::requiredIf(fn () => $actor->isSuperAdmin()),
                'nullable',
                'integer',
                'exists:libraries,id',
            ],
        ]);

        abort_unless($libraryId && Library::query()->whereKey($libraryId)->where('is_active', true)->exists(), 404);

        if (! $actor->isSuperAdmin()) {
            abort_unless($actor->belongsToLibrary($libraryId), 403);
        }

        if (blank($user->membership_number)) {
            $user->forceFill([
                'membership_number' => UserManagement::generateMembershipNumber(),
            ])->save();
        }

        LibraryMembership::query()->updateOrCreate(
            [
                'library_id' => $libraryId,
                'user_id' => $user->id,
            ],
            [
                'membership_number' => $user->membership_number ?: UserManagement::generateMembershipNumber(),
                'is_active' => true,
                'joined_at' => now(),
            ]
        );

        return back()->with('success', 'Bibliotekos narystė pridėta.');
    }

    public function toggle(Request $request, User $user, LibraryMembership $membership): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $membership->user_id === $user->id, 404);
        abort_unless($user->role === 'narys', 404);
        abort_unless($actor->isSuperAdmin() || $actor->belongsToLibrary($membership->library_id), 403);
        abort_unless($actor->isSuperAdmin() || UserManagement::canManageRole($actor, $user->role), 404);

        $membership->update([
            'is_active' => ! $membership->is_active,
        ]);

        return back()->with('success', $membership->is_active ? 'Narystė aktyvuota.' : 'Narystė deaktyvuota.');
    }

    public function destroy(Request $request, User $user, LibraryMembership $membership): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $membership->user_id === $user->id, 404);
        abort_unless($user->role === 'narys', 404);
        abort_unless($actor->isSuperAdmin() || $actor->belongsToLibrary($membership->library_id), 403);
        abort_unless($actor->isSuperAdmin() || UserManagement::canManageRole($actor, $user->role), 404);

        $membership->delete();

        return back()->with('success', 'Narystė pašalinta.');
    }
}








