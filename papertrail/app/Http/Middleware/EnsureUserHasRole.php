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

        if ($user->isOfficeDocumentActor() && ! $user->isOfficeDocumentPreparer()
            && (in_array('head-office', $roles, true) || in_array('head_office', $roleCodes, true))
            && ! $user->canManageSvpDocuments()
            && $this->isSvpPreparationRoute($request)) {
            return redirect()->route('head-office.dashboard')
                ->with('error', 'Procurement Staff manages SVP preparation for your office. Use Signatures to review and approve documents.');
        }

        if ($user->isOfficeDocumentPreparer()
            && (in_array('head-office', $roles, true) || in_array('head_office', $roleCodes, true))
            && ! $this->allowsOfficeDocumentPreparerRoute($request)) {
            AuditLogger::log('Access Control', 'Unauthorized Access Attempt', 'Procurement Staff attempted to access a Head of Office-only route.', null, null, null, 'warning', [
                'attempted_url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
            ]);

            return redirect()
                ->route('head-office.dashboard')
                ->with('error', 'This page is only available to the Head of Office.');
        }

        if (!in_array($user->roleSlug(), $roles, true)
            && !in_array($user->assignedRole?->code, $roleCodes, true)
            && !collect($roleCodes)->contains(fn ($role) => $user->hasRole($role))) {
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

    private function allowsOfficeDocumentPreparerRoute(Request $request): bool
    {
        if ($request->routeIs('head-office.inspection.accept', 'head-office.inspection.complete')) {
            return false;
        }

        return $request->routeIs(
            'head-office.dashboard',
            'head-office.ppmps.menu',
            'head-office.purchase-requests.menu',
            'head-office.my-documents.menu',
            'head-office.returned-documents.menu',
            'head-office.documents.*',
            'head-office.returned.*',
            'head-office.ppmp.*',
            'head-office.pr.*',
            'head-office.resolutions.*',
            'head-office.rfqs.*',
            'head-office.abstracts.*',
            'head-office.purchase-orders.*',
            'head-office.inspection.*',
            'head-office.supplemental-apps.*',
            'head-office.svp-tracking.*',
        );
    }

    private function isSvpPreparationRoute(Request $request): bool
    {
        if (! $request->routeIs(
            'head-office.ppmp.*', 'head-office.pr.*', 'head-office.resolutions.*',
            'head-office.rfqs.*', 'head-office.abstracts.*', 'head-office.purchase-orders.*',
            'head-office.inspection.*',
        ) || $request->routeIs('head-office.inspection.accept', 'head-office.inspection.complete')) {
            return false;
        }

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return true;
        }

        return $request->routeIs('*.create', '*.edit', 'head-office.inspection.index');
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
