<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditTrailController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Audit Trail', 'Audit Trail Viewed', 'Admin viewed the audit trail.');

        $query = $this->filteredQuery($request)->with('user')->latest();

        return view('admin.audit.index', [
            'logs' => $query->paginate(25)->withQueryString(),
            'filters' => $request->only([
                'search',
                'user',
                'role',
                'office',
                'module',
                'action',
                'document_type',
                'status',
                'severity',
                'date_from',
                'date_to',
            ]),
            'modules' => $this->optionList('module'),
            'actions' => $this->optionList('action'),
            'roles' => $this->mergedOptionList('role_name', 'user_role'),
            'offices' => $this->mergedOptionList('office_name', 'user_office'),
            'documentTypes' => $this->optionList('document_type'),
            'statuses' => ['success', 'failed', 'warning', 'denied', 'skipped'],
            'severities' => ['info', 'notice', 'warning', 'critical'],
            'summary' => $this->summary(),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        AuditLogger::log('Audit Trail', 'Audit Detail Viewed', 'Admin viewed an audit log detail.', $auditLog);

        return view('admin.audit.show', ['auditLog' => $auditLog]);
    }

    public function print(Request $request): View
    {
        AuditLogger::log('Audit Trail', 'Audit Trail Printed', 'Admin opened filtered audit trail print view.', null, null, null, 'notice', [
            'filters' => $request->query(),
        ]);

        return view('admin.audit.print', [
            'logs' => $this->filteredQuery($request)->latest()->limit(500)->get(),
            'filters' => $request->query(),
            'generatedAt' => now(),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        AuditLogger::log('Audit Trail', 'Audit Trail Exported', 'Admin exported filtered audit logs.', null, null, null, 'notice', [
            'filters' => $request->query(),
        ]);

        $filename = 'papertrail-audit-trail-' . now()->format('Y-m-d') . '.csv';
        $query = $this->filteredQuery($request)->latest();

        return Response::streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'created_at',
                'user_identifier',
                'user_name',
                'role_name',
                'office_name',
                'action',
                'module',
                'document_type',
                'tracking_number',
                'document_reference_number',
                'status',
                'severity',
                'ip_address',
                'description',
            ]);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->created_at?->format('Y-m-d H:i:s'),
                        $log->user_identifier,
                        $log->user_name,
                        $log->role_name ?? $log->user_role,
                        $log->office_name ?? $log->user_office,
                        $log->action,
                        $log->module,
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

    private function filteredQuery(Request $request): Builder
    {
        $query = AuditLog::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('event_uuid', 'like', "%{$search}%")
                    ->orWhere('user_identifier', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('user_role', 'like', "%{$search}%")
                    ->orWhere('role_name', 'like', "%{$search}%")
                    ->orWhere('user_office', 'like', "%{$search}%")
                    ->orWhere('office_name', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('target_label', 'like', "%{$search}%")
                    ->orWhere('document_type', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('document_reference_number', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('severity', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('route_name', 'like', "%{$search}%")
                    ->orWhere('request_url', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        foreach (['module', 'action', 'document_type', 'status', 'severity'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field)->toString());
            }
        }

        if ($request->filled('user')) {
            $user = $request->string('user')->toString();
            $query->where(function (Builder $builder) use ($user) {
                $builder->where('user_identifier', 'like', "%{$user}%")
                    ->orWhere('user_name', 'like', "%{$user}%");
            });
        }

        if ($request->filled('role')) {
            $role = $request->string('role')->toString();
            $query->where(fn (Builder $builder) => $builder->where('role_name', $role)->orWhere('user_role', $role));
        }

        if ($request->filled('office')) {
            $office = $request->string('office')->toString();
            $query->where(fn (Builder $builder) => $builder->where('office_name', $office)->orWhere('user_office', $office));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date('date_from'))->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date('date_to'))->endOfDay());
        }

        return $query;
    }

    private function optionList(string $field)
    {
        return AuditLog::query()
            ->whereNotNull($field)
            ->select($field)
            ->distinct()
            ->orderBy($field)
            ->pluck($field)
            ->filter()
            ->values();
    }

    private function mergedOptionList(string $primary, string $fallback)
    {
        return $this->optionList($primary)
            ->merge($this->optionList($fallback))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function summary(): array
    {
        return [
            'today' => AuditLog::whereDate('created_at', today())->count(),
            'workflow' => AuditLog::where(function (Builder $query) {
                $query->where('module', 'like', '%Workflow%')
                    ->orWhere('module', 'like', '%Routing%')
                    ->orWhere('module', 'like', '%SVP%')
                    ->orWhere('action', 'like', '%Routed%')
                    ->orWhere('action', 'like', '%Submitted%')
                    ->orWhere('action', 'like', '%Approved%')
                    ->orWhere('action', 'like', '%Returned%');
            })->count(),
            'failed_denied' => AuditLog::whereIn('status', ['failed', 'denied'])
                ->orWhere('action', 'like', '%Unauthorized%')
                ->orWhere('action', 'like', '%Failed%')
                ->count(),
            'critical' => AuditLog::where('severity', 'critical')->count(),
        ];
    }
}
