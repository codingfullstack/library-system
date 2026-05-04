<?php

namespace App\Http\Responses;

use App\Support\Auth\ResolvesHomeRoute;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();

        return redirect()->intended(route(ResolvesHomeRoute::routeName($user), absolute: false));
    }
}
