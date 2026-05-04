<?php

namespace App\Http\Middleware;

use App\Actions\Notifications\EnsureOverdueLoanNotificationsAction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DispatchOverdueLoanNotifications
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            app(EnsureOverdueLoanNotificationsAction::class)->handle($user);
        }

        return $next($request);
    }
}
