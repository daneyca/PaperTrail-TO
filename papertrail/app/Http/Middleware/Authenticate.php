<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Authenticate extends Middleware
{
    public function handle($request, \Closure $next, ...$guards): Response
    {
        return parent::handle($request, function ($request) use ($next) {
            $account = $request->user();
            // Old BAC sessions must not become a way into the new personal login.
            if (! $account?->isActive() || $account->principal_user_id) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson()
                    ? response()->json(['message' => 'This account is inactive.'], 403)
                    : redirect()->route('login');
            }

            $request->attributes->set('login_account', $account);
            $mpdo = app(\App\Services\BacsecMpdoWorkspaceService::class);
            $workspaceId = $request->session()->get('active_workspace_id', $account->id);
            $workspace = $account;
            if ((int) $workspaceId !== (int) $account->id && ! $request->routeIs('workspaces.switch', 'logout', 'session.timeout', 'switch-account')) {
                $workspace = $account->linkedWorkspaces()->whereKey($workspaceId)->where('status', \App\Models\User::STATUS_ACTIVE)->first();
                if (! $workspace) {
                    $request->session()->forget('active_workspace_id');
                    return $request->isMethod('GET')
                        ? redirect()->route('dashboard')->with('warning', 'That workspace is no longer available.')
                        : abort(409, 'Your workspace changed. Reload the page before continuing.');
                }
                $workspace->setRelation('principalAccount', $account);
                if ($mpdo->allows($account) && $workspace->user_id === \App\Services\BacsecMpdoWorkspaceService::WORKSPACE) {
                    $request->session()->forget('active_workspace_id');
                    $workspace = $account;
                }
            }

            try {
                $workspace = $mpdo->forRequest($account, $request) ?: $workspace;
            } catch (\Illuminate\Validation\ValidationException $exception) {
                return redirect()->route('bac-secretariat.dashboard')
                    ->with('error', collect($exception->errors())->flatten()->first());
            }

            auth()->setUser($workspace);
            try {
                return $next($request);
            } finally {
                // Only the principal is persisted in the login session, never the selected role.
                if (auth()->hasUser()) {
                    auth()->setUser($account);
                }
            }
        }, ...$guards);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login');
    }
}
