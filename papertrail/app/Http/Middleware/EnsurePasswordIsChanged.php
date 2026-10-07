<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    private const ALLOWED_ROUTES = [
        'profile.show',
        'profile.password.update',
        'verification.code.send',
        'verification.code.verify',
        'logout',
        'switch-account',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->must_change_password || $request->routeIs(self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        return redirect()
            ->route('profile.show')
            ->with('warning', 'You must change your temporary password before continuing.');
    }
}
