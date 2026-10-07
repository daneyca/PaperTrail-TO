@extends('layouts.dashboard')

@section('title', 'Pending Approval | PaperTrail')

@section('content')
    @php
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">Head of the Procuring Entity</p>
            <h1>Pending Approval</h1>
            <p>Review and approve procurement documents forwarded for final authorization.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern pt-smooth-enter" style="--pt-delay: 120ms" aria-label="Approval summary">
        <x-dashboard.stat-card href="{{ route('approving-authority.pending.index') }}" value="{{ $summary['pending'] }}" label="Pending Approval" accent="gold" class="{{ blank($activeStatus) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('approving-authority.pending.index', ['status' => \App\Models\ProcurementDocument::STATUS_UNDER_APPROVAL]) }}" value="{{ $summary['underReview'] }}" label="Under Review" accent="blue" class="{{ $activeStatus === \App\Models\ProcurementDocument::STATUS_UNDER_APPROVAL ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('approving-authority.approved.index') }}" value="{{ $summary['approved'] }}" label="Approved" accent="green" />
        <x-dashboard.stat-card href="{{ route('approving-authority.returned.index') }}" value="{{ $summary['returned'] }}" label="Returned" accent="red" />
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 220ms" aria-label="Pending approval documents">
        <form method="GET" action="{{ route('approving-authority.pending.index') }}" class="budget-filter-toolbar">
            <div class="user-search">
                <label for="approval-search">Search documents</label>
                <input id="approval-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or requesting office">
            </div>
            <div class="user-filter"><label for="document-type">Document Type</label><select id="document-type" name="document_type"><option value="">All types</option>@foreach ($documentTypes as $type)<option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="fiscal-year">Fiscal Year</label><select id="fiscal-year" name="fiscal_year"><option value="">All years</option>@foreach ($fiscalYears as $year)<option value="{{ $year }}" @selected((string) ($filters['fiscal_year'] ?? '') === (string) $year)>{{ $year }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="status-filter">Status</label><select id="status-filter" name="status"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label($status) }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="priority-filter">Priority</label><select id="priority-filter" name="priority"><option value="">All priorities</option>@foreach (['normal' => 'Normal', 'urgent' => 'Urgent', 'high' => 'High', 'low' => 'Low'] as $value => $text)<option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $text }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="date-from">Date From</label><input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="user-filter"><label for="date-to">Date To</label><input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="filter-actions"><button type="submit">Apply</button><a href="{{ route('approving-authority.pending.index') }}">Clear</a></div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead><tr><th>Tracking Number</th><th>Document Type</th><th>Title</th><th>Requesting Office</th><th>Total Amount</th><th>BAC Chair Decision</th><th>Current Status</th><th>Current Stage</th><th>Forwarded Date</th><th>Pending Days</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td><strong>{{ $document->title }}</strong></td>
                            <td>{{ $document->requesting_office }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td>{{ $label($document->decision) }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $label($document->status) }}</span></td>
                            <td>{{ $document->stage ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $document->forwarded_date?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $document->pending_days }}</td>
                            <td><div class="table-actions"><a href="{{ $document->action_url }}">View / Approve</a></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><div class="empty-state"><strong>No pending approvals</strong><p>Documents forwarded by the BAC Chair for final authorization will appear here.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($documents->hasPages())
            <div class="pagination-wrap">{{ $documents->appends(request()->query())->links('vendor.pagination.papertrail') }}</div>
        @endif
    </section>
@endsection
