<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemSettingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    private array $activeReports = ['users', 'offices', 'roles', 'audit', 'access', 'system-summary'];

    public function index(): View
    {
        AuditLogger::log('Reports', 'Report Viewed', 'Reports dashboard viewed.');

        return view('admin.reports.index', [
            'summary' => [
                'users' => User::whereNotNull('user_id')->count(),
                'activeUsers' => User::where('status', 'active')->count(),
                'offices' => Office::count(),
                'activeRoles' => Role::where('status', 'active')->count(),
                'todayAudit' => AuditLog::whereDate('created_at', today())->count(),
                'unauthorized' => AuditLog::where('action', 'Unauthorized Access Attempt')->count(),
            ],
        ]);
    }

    public function users(Request $request): View { return $this->tableReport($request, 'users'); }
    public function offices(Request $request): View { return $this->tableReport($request, 'offices'); }
    public function roles(Request $request): View { return $this->tableReport($request, 'roles'); }
    public function audit(Request $request): View { return $this->tableReport($request, 'audit'); }
    public function access(Request $request): View { return $this->tableReport($request, 'access'); }

    public function systemSummary(): View
    {
        AuditLogger::log('Reports', 'Report Viewed', 'System Summary Report viewed.');

        return view('admin.reports.system-summary', [
            'lguName' => SystemSettingService::get('lgu.name', 'Municipality of Tomas Oppus'),
            'summary' => [
                'Total users' => User::whereNotNull('user_id')->count(),
                'Active users' => User::where('status', 'active')->count(),
                'Inactive users' => User::where('status', 'inactive')->count(),
                'Total offices' => Office::count(),
                'Active offices' => Office::where('status', 'active')->count(),
                'Total roles' => Role::count(),
                'Active roles' => Role::where('status', 'active')->count(),
                'Total permissions' => \App\Models\Permission::count(),
                'Total audit logs' => AuditLog::count(),
                "Today's audit logs" => AuditLog::whereDate('created_at', today())->count(),
                'Warning events' => AuditLog::where('severity', 'warning')->count(),
                'Critical events' => AuditLog::where('severity', 'critical')->count(),
                'Unauthorized attempts' => AuditLog::where('action', 'Unauthorized Access Attempt')->count(),
            ],
            'modules' => AuditLog::selectRaw('module, count(*) as total')->groupBy('module')->orderByDesc('total')->limit(5)->get(),
            'activities' => AuditLog::latest()->limit(8)->get(),
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, $this->activeReports, true), 404);
        AuditLogger::log('Reports', 'Report Exported', ucfirst($type) . ' report exported.', null, null, null, 'info', ['type' => $type]);
        $rows = $type === 'system-summary'
            ? collect($this->systemSummaryRows())
            : $this->rows($request, $type)->get();
        $filename = 'papertrail-' . $type . '-report-' . now()->format('Y-m-d') . '.csv';

        return Response::streamDownload(function () use ($rows, $type) {
            $handle = fopen('php://output', 'w');
            $headers = $type === 'system-summary' ? ['Metric', 'Value'] : $this->headers($type);
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $type === 'system-summary' ? [$row['metric'], $row['value']] : $this->mapRow($type, $row));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function print(Request $request, string $type): View
    {
        abort_unless(in_array($type, $this->activeReports, true), 404);
        AuditLogger::log('Reports', 'Report Printed', ucfirst($type) . ' report print view opened.', null, null, null, 'info', ['type' => $type]);

        return view('admin.reports.print', [
            'type' => $type,
            'title' => $this->title($type),
            'lguName' => SystemSettingService::get('lgu.name', 'Municipality of Tomas Oppus'),
            'rows' => $type === 'system-summary'
                ? collect($this->systemSummaryRows())
                : $this->rows($request, $type)->limit(500)->get(),
            'headers' => $type === 'system-summary' ? ['Metric', 'Value'] : $this->headers($type),
        ]);
    }

    private function tableReport(Request $request, string $type): View
    {
        AuditLogger::log('Reports', 'Report Viewed', $this->title($type) . ' viewed.', null, null, null, 'info', ['type' => $type]);

        return view('admin.reports.table', [
            'type' => $type,
            'title' => $this->title($type),
            'rows' => $this->rows($request, $type)->paginate(10)->withQueryString(),
            'headers' => $this->headers($type),
        ]);
    }

    private function rows(Request $request, string $type): Builder
    {
        $query = match ($type) {
            'users' => User::query()->whereNotNull('user_id'),
            'offices' => Office::query()->withCount('users'),
            'roles' => Role::query()->withCount(['users', 'permissions']),
            'audit' => AuditLog::query(),
            'access' => AuditLog::query()->whereIn('action', ['Successful Login', 'Failed Login Attempt', 'Logout', 'Inactive Account Login Rejected', 'Unauthorized Access Attempt']),
            'system-summary' => AuditLog::query()->whereRaw('1 = 0'),
            default => abort(404),
        };

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search, $type) {
                match ($type) {
                    'users' => $builder->where('user_id', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('office', 'like', "%{$search}%"),
                    'offices' => $builder->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"),
                    'roles' => $builder->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"),
                    default => $builder->where('user_identifier', 'like', "%{$search}%")->orWhere('user_name', 'like', "%{$search}%")->orWhere('module', 'like', "%{$search}%")->orWhere('action', 'like', "%{$search}%"),
                };
            });
        }

        return $query->latest('created_at');
    }

    private function title(string $type): string
    {
        return match ($type) {
            'users' => 'User Accounts Report',
            'offices' => 'Offices Report',
            'roles' => 'Roles and Permissions Report',
            'audit' => 'Audit Trail Report',
            'access' => 'Login Activity / Access Report',
            'system-summary' => 'System Summary Report',
        };
    }

    private function headers(string $type): array
    {
        return match ($type) {
            'users' => ['User ID', 'Name', 'Office', 'Role', 'Status', 'Created Date', 'Last Updated'],
            'offices' => ['Code', 'Name', 'Type', 'Status', 'Assigned Users', 'Created Date'],
            'roles' => ['Code', 'Name', 'Status', 'Type', 'Assigned Users', 'Permissions', 'Created Date'],
            default => ['Date and Time', 'User ID', 'Name', 'Role', 'Office', 'Module', 'Action', 'Severity', 'IP Address'],
        };
    }

    private function mapRow(string $type, mixed $row): array
    {
        return match ($type) {
            'users' => [$row->user_id, $row->name, $row->office, $row->role, $row->status, $row->created_at, $row->updated_at],
            'offices' => [$row->code, $row->name, $row->type, $row->status, $row->users_count, $row->created_at],
            'roles' => [$row->code, $row->name, $row->status, $row->is_system ? 'System' : 'Custom', $row->users_count, $row->permissions_count, $row->created_at],
            default => [$row->created_at, $row->user_identifier, $row->user_name, $row->user_role, $row->user_office, $row->module, $row->action, $row->severity, $row->ip_address],
        };
    }

    private function systemSummaryRows(): array
    {
        return [
            ['metric' => 'Total users', 'value' => User::whereNotNull('user_id')->count()],
            ['metric' => 'Active users', 'value' => User::where('status', 'active')->count()],
            ['metric' => 'Inactive users', 'value' => User::where('status', 'inactive')->count()],
            ['metric' => 'Total offices', 'value' => Office::count()],
            ['metric' => 'Active offices', 'value' => Office::where('status', 'active')->count()],
            ['metric' => 'Total roles', 'value' => Role::count()],
            ['metric' => 'Active roles', 'value' => Role::where('status', 'active')->count()],
            ['metric' => 'Total permissions', 'value' => \App\Models\Permission::count()],
            ['metric' => 'Total audit logs', 'value' => AuditLog::count()],
            ['metric' => "Today's audit logs", 'value' => AuditLog::whereDate('created_at', today())->count()],
            ['metric' => 'Warning events', 'value' => AuditLog::where('severity', 'warning')->count()],
            ['metric' => 'Critical events', 'value' => AuditLog::where('severity', 'critical')->count()],
            ['metric' => 'Unauthorized attempts', 'value' => AuditLog::where('action', 'Unauthorized Access Attempt')->count()],
        ];
    }
}
