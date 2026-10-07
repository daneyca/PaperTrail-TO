@extends('layouts.dashboard')

@section('title', 'Audit Trail | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Accountability</p>
            <h1>Audit Trail</h1>
            <p>Monitor security events, workflow actions, document activity, and administrative changes.</p>
        </div>
        <div class="hero-actions">
            <x-ui.action-button
                :href="route('admin.audit.export', request()->query())"
                icon="download"
                label="Download Audit CSV"
                tooltip="Download Audit CSV"
                variant="download"
            />
            <x-ui.action-button
                :href="route('admin.audit.print', request()->query())"
                icon="print"
                label="Print Audit Trail"
                tooltip="Print Audit Trail"
                variant="print"
                target="_blank"
                rel="noopener"
            />
        </div>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="Audit summary">
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['today'] }}" label="Total Logs Today" accent="navy" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['workflow'] }}" label="Workflow Actions" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['failed_denied'] }}" label="Failed / Denied" accent="red" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['critical'] }}" label="Critical Events" accent="red" />
    </section>

    <section class="table-panel">
        <form method="GET" action="{{ route('admin.audit.index') }}" class="audit-filter-toolbar">
            <div class="user-search">
                <label for="audit-search">Search logs</label>
                <input id="audit-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="User, action, reference, tracking number, route, IP">
            </div>
            <div class="user-filter"><label>User</label><input name="user" type="search" value="{{ $filters['user'] ?? '' }}" placeholder="ID or name"></div>
            <div class="user-filter"><label>Role</label><select name="role"><option value="">All roles</option>@foreach($roles as $role)<option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ $role }}</option>@endforeach</select></div>
            <div class="user-filter"><label>Office</label><select name="office"><option value="">All offices</option>@foreach($offices as $office)<option value="{{ $office }}" @selected(($filters['office'] ?? '') === $office)>{{ $office }}</option>@endforeach</select></div>
            <div class="user-filter"><label>Module</label><select name="module"><option value="">All modules</option>@foreach($modules as $module)<option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>@endforeach</select></div>
            <div class="user-filter"><label>Action</label><select name="action"><option value="">All actions</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>@endforeach</select></div>
            <div class="user-filter"><label>Document Type</label><select name="document_type"><option value="">All types</option>@foreach($documentTypes as $type)<option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
            <div class="user-filter"><label>Status</label><select name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="user-filter"><label>Severity</label><select name="severity"><option value="">All severities</option>@foreach($severities as $severity)<option value="{{ $severity }}" @selected(($filters['severity'] ?? '') === $severity)>{{ ucfirst($severity) }}</option>@endforeach</select></div>
            <div class="user-filter"><label>Date From</label><input name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="user-filter"><label>Date To</label><input name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('admin.audit.index') }}">Clear</a>
            </div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Date / Time</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Office</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Target / Document</th>
                        <th>Status</th>
                        <th>Severity</th>
                        <th>IP Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="nowrap">{{ $log->created_at?->format('M d, Y h:i A') }}</td>
                            <td>{{ $log->user_identifier ?? 'Guest' }}<br><span class="muted-text">{{ $log->user_name ?? 'N/A' }}</span></td>
                            <td>{{ $log->role_name ?? $log->user_role ?? 'N/A' }}</td>
                            <td>{{ $log->office_name ?? $log->user_office ?? 'N/A' }}</td>
                            <td>{{ $log->action }}</td>
                            <td>{{ $log->module }}</td>
                            <td>
                                {{ $log->target_label ?? $log->tracking_number ?? 'N/A' }}
                                @if ($log->document_type)
                                    <br><span class="muted-text">{{ $log->document_type }}</span>
                                @endif
                                @if ($log->document_reference_number)
                                    <br><span class="muted-text">Tracking Number {{ $log->document_reference_number }}</span>
                                @endif
                            </td>
                            <td><span class="severity-badge severity-{{ $log->status ?? 'success' }}">{{ ucfirst($log->status ?? 'success') }}</span></td>
                            <td><span class="severity-badge severity-{{ $log->severity }}">{{ ucfirst($log->severity) }}</span></td>
                            <td class="nowrap">{{ $log->ip_address ?? 'N/A' }}</td>
                            <td>
                                <div class="table-actions">
                                    <x-ui.action-button
                                        :href="route('admin.audit.show', $log)"
                                        icon="view"
                                        label="View Details"
                                        tooltip="View Details"
                                        variant="view"
                                        icon-only
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><div class="empty-state"><strong>No audit logs found</strong><p>Try adjusting the search text or filters.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="pagination-wrap">
                {{ $logs->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
