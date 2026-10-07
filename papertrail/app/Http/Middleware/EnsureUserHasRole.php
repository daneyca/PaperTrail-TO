<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        $roleCodes = collect($roles)->flatMap(fn ($role) => [$role, str_replace('-', '_', $role)])->all();

        if (!in_array($user->roleSlug(), $roles, true)
            && !in_array($user->assignedRole?->code, $roleCodes, true)) {
            if ($this->allowsBacsec002PurchaseRequestRoutes($request, $user, $roles, $roleCodes)) {
                return $next($request);
            }

            AuditLogger::log('Access Control', 'Unauthorized Access Attempt', 'User attempted to access a restricted route.', null, null, null, 'warning', [
                'required_roles' => $roles,
                'attempted_url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
            ]);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Unauthorized access.');
        }

        return $next($request);
    }

    private function allowsBacsec002PurchaseRequestRoutes(Request $request, mixed $user, array $roles, array $roleCodes): bool
    {
        if (! in_array('head-office', $roles, true) && ! in_array('head_office', $roleCodes, true)) {
            return false;
        }

        if (! method_exists($user, 'hasBacsec002PurchaseRequestCapability') || ! $user->hasBacsec002PurchaseRequestCapability()) {
            return false;
        }

        return $request->routeIs(
            'head-office.purchase-requests.menu',
            'head-office.pr.*',
            'head-office.svp-tracking.*',
        );
    }
}
