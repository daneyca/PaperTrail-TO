<?php

namespace App\Http\Middleware;

use App\Models\Office;
use App\Models\User;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequestingOfficeForHeadOfficeTransactions
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! ($user->hasRole('head_office') || $user->hasRole(User::ROLE_HEAD_OFFICE))) {
            return $next($request);
        }

        if (! $this->isRestrictedTransactionRoute($request)) {
            return $next($request);
        }

        $office = $user->assignedOffice ?: ($user->office_id ? Office::find($user->office_id) : null);

        if ($office?->isRequestingOffice()) {
            return $next($request);
        }

        AuditLogger::log(
            'Head Office Access',
            'Non-requesting office transaction blocked',
            'A Head Office account assigned to a non-requesting office attempted to access an End User transaction action.',
            $user,
            null,
            ['office_id' => $user->office_id, 'route' => $request->route()?->getName()],
            'warning'
        );

        $message = 'This office is not configured as a requesting/end-user office for new procurement transactions.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()
            ->route('head-office.dashboard')
            ->with('error', $message);
    }

    private function isRestrictedTransactionRoute(Request $request): bool
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return true;
        }

        return $request->routeIs(
            'ppmps.create',
            'ppmps.edit',
            'annual-procurement-plans.create',
            'annual-procurement-plans.edit',
            'head-office.ppmp.create',
            'head-office.ppmp.edit',
            'head-office.pr.create',
            'head-office.pr.edit',
            'head-office.rfqs.create',
            'head-office.rfqs.edit',
            'head-office.abstracts.create',
            'head-office.abstracts.edit',
            'head-office.purchase-orders.create',
            'head-office.purchase-orders.edit'
        );
    }
}
