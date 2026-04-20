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
        $user = $request->user();

        if ($user) {
            app(LibraryContext::class)->set(
                $user->library_id,
                $user->role === 'super_admin'
            );
        } else {
            app(LibraryContext::class)->clear();
        }

        return $next($request);
    }
}

