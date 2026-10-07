@extends('layouts.dashboard')

@section('title', 'Reports | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero reports-compact-header">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Reports</h1>
            <p>Generate, export, and print administrative and procurement-related summaries.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern reports-summary-strip" aria-label="Reports summary">
        <x-dashboard.stat-card href="{{ route('admin.reports.users') }}" value="{{ $summary['users'] }}" label="Total Users" accent="navy" class="report-summary-item" />
        <x-dashboard.stat-card href="{{ route('admin.reports.users', ['status' => 'active']) }}" value="{{ $summary['activeUsers'] }}" label="Active Users" accent="green" class="report-summary-item" />
        <x-dashboard.stat-card href="{{ route('admin.reports.offices') }}" value="{{ $summary['offices'] }}" label="Offices" accent="gold" class="report-summary-item" />
        <x-dashboard.stat-card href="{{ route('admin.reports.audit') }}" value="{{ $summary['todayAudit'] }}" label="Audit Logs Today" accent="blue" class="report-summary-item" />
    </section>

    @php
        $reports = [
            ['title' => 'User Accounts Report', 'category' => 'Administration', 'description' => 'Fixed user IDs, names, offices, roles, account status, and account dates.', 'route' => 'admin.reports.users', 'type' => 'users'],
            ['title' => 'Offices Report', 'category' => 'Administration', 'description' => 'LGU offices, office types, status, and assigned user counts.', 'route' => 'admin.reports.offices', 'type' => 'offices'],
            ['title' => 'Roles and Permissions Report', 'category' => 'Access Control', 'description' => 'Role status, system role markers, assigned users, and permission counts.', 'route' => 'admin.reports.roles', 'type' => 'roles'],
            ['title' => 'Audit Trail Report', 'category' => 'Audit', 'description' => 'Administrative activity logs with module, action, severity, and IP address.', 'route' => 'admin.reports.audit', 'type' => 'audit'],
            ['title' => 'Login Activity / Access Report', 'category' => 'Security', 'description' => 'Successful logins, failed attempts, logout events, and unauthorized access attempts.', 'route' => 'admin.reports.access', 'type' => 'access'],
            ['title' => 'System Summary Report', 'category' => 'System', 'description' => 'High-level counts for current administrative records and audit activity.', 'route' => 'admin.reports.system-summary', 'type' => 'system-summary'],
        ];

        $futureReports = [
            ['title' => 'Procurement Documents Report', 'category' => 'Documents'],
            ['title' => 'PPMP / APP Report', 'category' => 'Planning'],
            ['title' => 'Purchase Request Report', 'category' => 'Procurement'],
            ['title' => 'Purchase Order Report', 'category' => 'Procurement'],
            ['title' => 'AI Checking Report', 'category' => 'AI Assistance'],
            ['title' => 'Chatbot Usage Report', 'category' => 'Support'],
        ];
    @endphp

    <section class="table-panel report-registry-panel report-panel-compact" aria-label="Available reports">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Report Registry</p>
                <h2>Available Administrative Reports</h2>
                <p>Open, export, or print supported reports from one compact registry.</p>
            </div>
        </div>

        <div class="report-registry-list">
            @foreach ($reports as $report)
                <div class="report-registry-row">
                    <div class="report-registry-main">
                        <span class="report-category">{{ $report['category'] }}</span>
                        <strong>{{ $report['title'] }}</strong>
                        <p>{{ $report['description'] }}</p>
                    </div>

                    <span class="status-pill status-active report-status-badge">Available</span>

                    <div class="report-row-actions">
                        <x-ui.action-button
                            :href="route($report['route'])"
                            icon="view"
                            label="View Report"
                            tooltip="View Report"
                            variant="view"
                        />
                        <x-ui.action-button
                            :href="route('admin.reports.export', $report['type'])"
                            icon="download"
                            label="Download Report"
                            tooltip="Download Report"
                            variant="download"
                        />
                        <x-ui.action-button
                            :href="route('admin.reports.print', $report['type'])"
                            icon="print"
                            label="Print Report"
                            tooltip="Print Report"
                            variant="print"
                            target="_blank"
                            rel="noopener"
                        />
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="table-panel future-report-panel report-panel-compact" aria-label="Future report placeholders">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Reserved for manuscript scope</p>
                <h2>Future Procurement Reports</h2>
                <p>These reports will become available after the procurement document modules are implemented.</p>
            </div>
        </div>

        <div class="future-report-list compact-report-list">
            @foreach ($futureReports as $report)
                <div class="future-report-row">
                    <div>
                        <span class="report-category">{{ $report['category'] }}</span>
                        <strong>{{ $report['title'] }}</strong>
                    </div>
                    <span class="status-pill status-inactive report-status-badge">Reserved</span>
                </div>
            @endforeach
        </div>
    </section>
@endsection
