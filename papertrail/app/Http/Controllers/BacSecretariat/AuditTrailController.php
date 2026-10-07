<?php

namespace App\Http\Controllers\BacSecretariat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditTrailController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('BAC Secretariat Audit Trail', 'Audit Trail Page Viewed', 'BAC Secretariat viewed scoped audit trail.');

        $base = $this->scopedQuery($request->user());
        $query = clone $base;

        $this->applyFilters($query, $request);

        return view('bac-secretariat.audit.index', [
            'logs' => $query->latest()->paginate(25)->withQueryString(),
            'summary' => $this->summary($base),
            'filters' => $request->only(['search', 'module', 'action', 'document_type', 'status', 'severity', 'date_from', 'date_to']),
            'modules' => $this->optionList((clone $base), 'module'),
            'actions' => $this->optionList((clone $base), 'action'),
            'documentTypes' => $this->optionList((clone $base), 'document_type'),
            'statuses' => ['success', 'failed', 'warning', 'denied', 'skipped'],
            'severities' => ['info', 'notice', 'warning', 'critical'],
        ]);
    }

    public function show(Request $request, AuditLog $auditLog): View|RedirectResponse
    {
        if (! $this->scopedQuery($request->user())->whereKey($auditLog->id)->exists()) {
            AuditLogger::denied('unauthorized_access_attempt', [
                'module' => 'BAC Secretariat Audit Trail',
                'description' => 'BAC Secretariat attempted to view an audit log outside scope.',
                'auditable' => $auditLog,
            ]);

            return redirect()
                ->route('bac-secretariat.audit.index')
                ->with('error', 'You are not authorized to view this audit event.');
        }

        AuditLogger::log('BAC Secretariat Audit Trail', 'Audit Detail Viewed', 'BAC Secretariat viewed scoped audit detail.', $auditLog);

        return view('bac-secretariat.audit.show', [
            'auditLog' => $auditLog,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        AuditLogger::log('BAC Secretariat Audit Trail', 'Audit Trail Exported', 'BAC Secretariat exported scoped audit trail.', null, null, null, 'notice');

        $query = $this->scopedQuery($request->user());
        $this->applyFilters($query, $request);

        $filename = 'papertrail-bac-secretariat-audit-trail-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'created_at',
                'user_identifier',
                'user_name',
                'module',
                'action',
                'document_type',
                'tracking_number',
                'document_reference_number',
                'status',
                'severity',
                'ip_address',
                'description',
            ]);

            $query->latest()->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->created_at?->format('Y-m-d H:i:s'),
                        $log->user_identifier,
                        $log->user_name,
                        $log->module,
                        $log->action,
                        $log->document_type,
                        $log->tracking_number,
                        $log->document_reference_number,
                        $log->status,
                        $log->severity,
                        $log->ip_address,
                        $log->description,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function scopedQuery(User $user): Builder
    {
        return AuditLog::query()
            ->where(function (Builder $builder) use ($user) {
                $builder->where('user_id', $user->id)
                    ->orWhere('user_role', User::ROLE_BAC_SECRETARIAT)
                    ->orWhere('role_name', User::ROLE_BAC_SECRETARIAT)
                    ->orWhereIn('module', $this->scopedModules())
                    ->orWhereIn('document_type', ['PR', 'Purchase Request', 'PPMP', 'APP', 'BAC Resolution', 'RFQ', 'Abstract', 'Purchase Order', 'Supplemental APP']);
            })
            ->whereNotIn('module', [
                'Authentication',
                'Security',
                'Access Control',
                'User Management',
                'Offices Management',
                'Roles Management',
                'Permissions',
                'Settings',
                'Admin Reports',
                'Audit Trail',
            ]);
    }

    private function scopedModules(): array
    {
        return [
            'BAC Secretariat',
            'BAC Secretariat Incoming Documents',
            'BAC Secretariat PPMP Review',
            'Document Routing',
            'APP Consolidation',
            'Supplemental APP',
            'Purchase Requests',
            'Purchase Orders',
            'BAC Resolutions',
            'RFQ',
            'Abstract',
            'SVP Monitoring',
            'SVP Chains',
            'BAC Secretariat Audit Trail',
            'BAC Secretariat Reports',
            'Notifications',
            'Profile',
            'Workflow',
        ];
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('user_name', 'like', "%{$search}%")
                    ->orWhere('user_identifier', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('target_label', 'like', "%{$search}%")
                    ->orWhere('document_type', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('document_reference_number', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('route_name', 'like', "%{$search}%")
                    ->orWhere('request_url', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        });

        foreach (['module', 'action', 'document_type', 'status', 'severity'] as $field) {
            $query->when($request->filled($field), fn (Builder $builder) => $builder->where($field, $request->input($field)));
        }

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('created_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('created_at', '<=', $request->date('date_to')));
    }

    private function optionList(Builder $query, string $field)
    {
        return $query
            ->whereNotNull($field)
            ->select($field)
            ->distinct()
            ->orderBy($field)
            ->pluck($field)
            ->filter()
            ->values();
    }

    private function summary(Builder $base): array
    {
        return [
            'total' => (clone $base)->count(),
            'today' => (clone $base)->whereDate('created_at', today())->count(),
            'routing' => (clone $base)->where(function (Builder $query) {
                $query->where('module', 'Document Routing')
                    ->orWhere('module', 'Workflow')
                    ->orWhere('action', 'like', '%Routed%')
                    ->orWhere('action', 'like', '%Route%');
            })->count(),
            'failed_denied' => (clone $base)->whereIn('status', ['failed', 'denied'])->count(),
        ];
    }
}
