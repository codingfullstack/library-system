<?php

namespace App\Http\Middleware;

use App\Support\LibraryContext;
use App\Support\UserManagement;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLibraryContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app(LibraryContext::class)->clear();

        $user = $request->user();

        if ($user) {
            if (! $user->is_active) {
                UserManagement::revokeAllAccess($user);

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Account is inactive.',
                        'code' => 'account_inactive',
                    ], 403);
                }

                abort(403, 'Paskyra neaktyvi.');
            }

            $requestedLibraryId = $request->integer('library_id') ?: (int) $request->header('X-Library-Id');

            if ($requestedLibraryId && ! $user->isSuperAdmin() && ! $user->belongsToLibrary($requestedLibraryId)) {
                abort(403, 'Neturite aktyvios narystės pasirinktoje bibliotekoje.');
            }

            $activeLibraryId = $requestedLibraryId
                ? $requestedLibraryId
                : $user->activeLibraryId();

            if ($activeLibraryId && $request->hasSession() && (int) $request->session()->get('active_library_id') !== (int) $activeLibraryId) {
                $request->session()->put('active_library_id', $activeLibraryId);
            }

            if (! $activeLibraryId && $request->hasSession()) {
                $request->session()->forget('active_library_id');
            }

            app(LibraryContext::class)->set($activeLibraryId, $user->isSuperAdmin());
        } else {
            app(LibraryContext::class)->clear();
        }

        return $next($request);
    }
}
