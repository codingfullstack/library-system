<?php

namespace App\Http\Controllers;

use App\Models\Library;
use App\Support\LibraryJoinService;
use App\Support\UserManagement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MemberLibraryController extends Controller
{
    public function join(Request $request, Library $library, LibraryJoinService $libraryJoinService): RedirectResponse|Response
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
            return response(LibraryJoinService::INACTIVE_MESSAGE, 403);
        }

        return redirect()
            ->route('account.dashboard')
            ->with('success', sprintf('Prisijungėte prie bibliotekos "%s".', $library->name));
    }
}
