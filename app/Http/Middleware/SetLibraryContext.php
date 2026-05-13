<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\LibraryContext;
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
            $requestedLibraryId = $request->integer('library_id') ?: (int) $request->header('X-Library-Id');
            $activeLibraryId = $requestedLibraryId && ($user->isSuperAdmin() || $user->belongsToLibrary($requestedLibraryId))
                ? $requestedLibraryId
                : $user->activeLibraryId();

            if ($activeLibraryId && $request->hasSession() && (int) $request->session()->get('active_library_id') !== (int) $activeLibraryId) {
                $request->session()->put('active_library_id', $activeLibraryId);
            }

            app(LibraryContext::class)->set($activeLibraryId, $user->isSuperAdmin());
        } else {
            app(LibraryContext::class)->clear();
        }

        return $next($request);
    }
}








