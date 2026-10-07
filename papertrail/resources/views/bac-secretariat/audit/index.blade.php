@extends('layouts.dashboard')

@section('title', 'BAC Secretariat Audit Trail | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>Audit Trail</h1>
            <p>Review recorded BAC Secretariat activities, routing actions, and document processing events.</p>
        </div>
        <div class="hero-actions">
            <x-ui.action-button
                :href="route('bac-secretariat.audit.export', request()->query())"
                icon="download"
                label="Download Audit CSV"
                tooltip="Download Audit CSV"
                variant="download"
            />
        </div>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="BAC Secretariat audit summary">
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['total'] }}" label="Total Logs" accent="navy" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['today'] }}" label="Today's Activities" accent="green" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['routing'] }}" label="Routing Actions" accent="gold" />
        <x-dashboard.stat-card href="{{ url()->current() }}" value="{{ $summary['failed_denied'] }}" label="Failed / Denied" accent="red" />
    </section>

    <section class="table-panel">
        <x-document-filter-card :action="route('bac-secretariat.audit.index')" class="app-filter-toolbar">
            <div class="user-search">
                <label for="audit-search">Search logs</label>
                <input id="audit-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="User, module, action, reference, tracking number, IP">
            </div>
            <div class="user-filter">
                <label for="module">Module</label>
                <select id="module" name="module">
                    <option value="">All modules</option>
                    @foreach ($modules as $module)
                        <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>
                    @endforeach
                </select>
            </div>
            <div class="user-filter">
                <label for="action">Action</label>
                <select id="action" name="action">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="user-filter">
                <label for="document_type">Document Type</label>
                <select id="document_type" name="document_type">
                    <option value="">All types</option>
                    @foreach ($documentTypes as $type)
                        <option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="user-filter">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="user-filter">
                <label for="severity">Severity</label>
                <select id="severity" name="severity">
                    <option value="">All severities</option>
                    @foreach ($severities as $severity)
                        <option value="{{ $severity }}" @selected(($filters['severity'] ?? '') === $severity)>{{ ucfirst($severity) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="user-filter"><label for="date-from">Date From</label><input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="user-filter"><label for="date-to">Date To</label><input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="filter-actions"><button type="submit">Apply</button><a href="{{ route('bac-secretariat.audit.index') }}">Clear</a></div>
        </x-document-filter-card>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Date and Time</th>
                        <th>User</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Target / Document</th>
                        <th>Status</th>
                        <th>Severity</th>
                        <th>IP Address</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="nowrap">{{ $log->created_at?->format('M d, Y h:i A') }}</td>
                            <td>{{ $log->user_identifier ?? 'System' }}<br><span class="muted-text">{{ $log->user_name ?? 'N/A' }}</span></td>
                            <td>{{ $log->module }}</td>
                            <td>{{ $log->action }}</td>
                            <td>
                                {{ $log->target_label ?? $log->tracking_number ?? 'N/A' }}
                                <br><span class="muted-text">{{ $log->document_type ?? $log->description ?? '' }}</span>
                                @if ($log->document_reference_number)
                                    <br><span class="muted-text">Tracking Number {{ $log->document_reference_number }}</span>
                                @endif
                            </td>
                            <td><span class="severity-badge severity-{{ $log->status ?? 'success' }}">{{ ucfirst($log->status ?? 'success') }}</span></td>
                            <td><span class="severity-badge severity-{{ $log->severity }}">{{ ucfirst($log->severity) }}</span></td>
                            <td class="nowrap">{{ $log->ip_address ?? 'N/A' }}</td>
                            <td><div class="table-actions"><a href="{{ route('bac-secretariat.audit.show', $log) }}">View Details</a></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty-state"><strong>No audit records yet</strong><p>BAC Secretariat actions will appear here once document processing activities are recorded.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="pagination-wrap">{{ $logs->appends(request()->query())->links('vendor.pagination.papertrail') }}</div>
        @endif
    </section>
@endsection
