<?php

use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\DispatchOverdueLoanNotifications;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SetLibraryContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AttachRequestId::class);
        $middleware->alias([
            'library.context' => SetLibraryContext::class,
            'role' => EnsureUserHasRole::class,
            'overdue.notifications' => DispatchOverdueLoanNotifications::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'code' => 'unauthenticated',
                ], 401);
            }

            return null;
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->expectsJson() && $request->routeIs('login.store')) {
                $seconds = (int) ($e->getHeaders()['Retry-After'] ?? 60);

                return back()->withErrors([
                    config('fortify.username', 'email') => trans('auth.throttle', [
                        'seconds' => $seconds,
                        'minutes' => ceil($seconds / 60),
                    ]),
                ]);
            }

            return null;
        });
    })->create();
