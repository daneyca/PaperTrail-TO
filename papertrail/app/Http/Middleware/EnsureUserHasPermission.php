<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user || (!$user->hasPermission($permission) && ! $this->allowsBacsec002PurchaseRequestPermission($request, $user, $permission))) {
            AuditLogger::log('Access Control', 'Unauthorized Access Attempt', 'User attempted to access a permission-protected route.', null, null, null, 'warning', [
                'required_permission' => $permission,
                'attempted_url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
            ]);

            return redirect()
                ->route('dashboard')
                ->with('error', 'Unauthorized access.');
        }

        return $next($request);
    }

    private function allowsBacsec002PurchaseRequestPermission(Request $request, mixed $user, string $permission): bool
    {
        if (! method_exists($user, 'hasBacsec002PurchaseRequestPermission') || ! $user->hasBacsec002PurchaseRequestPermission($permission)) {
            return false;
        }

        return $request->routeIs(
            'head-office.pr.*',
            'head-office.purchase-requests.menu',
            'head-office.svp-tracking.*',
        );
    }
}
