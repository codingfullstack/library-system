<?php

namespace App\Http\Controllers;

use App\Models\Library;
use App\Models\LibraryMembership;
use App\Support\UserManagement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberLibraryController extends Controller
{
    public function join(Request $request, Library $library): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->role === 'narys', 403);
        abort_unless($library->is_active && $library->is_public, 404);

        if (blank($user->membership_number)) {
            $user->forceFill([
                'membership_number' => UserManagement::generateMembershipNumber(),
            ])->save();
        }

        if (! $user->belongsToLibrary($library->id)) {
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
        }

        return redirect()
            ->route('account.dashboard')
            ->with('success', sprintf('Prisijungėte prie bibliotekos "%s".', $library->name));
    }
}








