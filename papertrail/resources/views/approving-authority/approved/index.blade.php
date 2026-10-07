@extends('layouts.dashboard')

@section('title', 'Approved Documents | PaperTrail')

@section('content')
    @php
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
        $activeOutcome = $filters['outcome'] ?? request('outcome');
        $activeDateFrom = $filters['date_from'] ?? request('date_from');
        $monthStart = now()->startOfMonth()->toDateString();
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">Head of the Procuring Entity</p>
            <h1>Approved Documents</h1>
            <p>View procurement documents approved by the Head of the Procuring Entity.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern pt-smooth-enter" style="--pt-delay: 120ms" aria-label="Approved documents summary">
        <x-dashboard.stat-card href="{{ route('approving-authority.approved.index') }}" value="{{ $summary['total'] }}" label="Total Approved" accent="green" class="{{ blank($activeOutcome) && blank($activeDateFrom) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('approving-authority.approved.index', ['outcome' => 'app_approved']) }}" value="{{ $summary['appApproved'] }}" label="APP Approved" accent="green" class="{{ $activeOutcome === 'app_approved' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('approving-authority.approved.index', ['outcome' => 'ready_for_po']) }}" value="{{ $summary['readyForPo'] }}" label="PRs Ready for PO" accent="blue" class="{{ $activeOutcome === 'ready_for_po' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('approving-authority.approved.index', ['date_from' => $monthStart]) }}" value="{{ $summary['thisMonth'] }}" label="Approved This Month" accent="green" class="{{ $activeDateFrom === $monthStart ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 220ms" aria-label="Approved documents">
        <form method="GET" action="{{ route('approving-authority.approved.index') }}" class="budget-filter-toolbar">
            <div class="user-search">
                <label for="approved-search">Search documents</label>
                <input id="approved-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or requesting office">
            </div>
            <div class="user-filter"><label for="document-type">Document Type</label><select id="document-type" name="document_type"><option value="">All types</option>@foreach ($documentTypes as $type)<option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="fiscal-year">Fiscal Year</label><select id="fiscal-year" name="fiscal_year"><option value="">All years</option>@foreach ($fiscalYears as $year)<option value="{{ $year }}" @selected((string) ($filters['fiscal_year'] ?? '') === (string) $year)>{{ $year }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="outcome-filter">Outcome</label><select id="outcome-filter" name="outcome"><option value="">All outcomes</option>@foreach ($outcomes as $value => $text)<option value="{{ $value }}" @selected(($filters['outcome'] ?? '') === $value)>{{ $text }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="status-filter">Current Status</label><select id="status-filter" name="status"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label($status) }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="date-from">Date From</label><input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="user-filter"><label for="date-to">Date To</label><input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="filter-actions"><button type="submit">Apply</button><a href="{{ route('approving-authority.approved.index') }}">Clear</a></div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead><tr><th>Tracking Number</th><th>Document Type</th><th>Title</th><th>Requesting Office</th><th>Total Amount</th><th>Outcome</th><th>Current Status</th><th>Approved Date</th><th>Current Office</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td><strong>{{ $document->title }}</strong></td>
                            <td>{{ $document->requesting_office }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td>{{ $document->outcome }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $label($document->status) }}</span></td>
                            <td class="nowrap">{{ $document->approved_at?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $document->current_office }}</td>
                            <td><div class="table-actions"><a href="{{ $document->action_url }}">View Details</a></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="empty-state"><strong>No approved documents yet</strong><p>Documents will appear here after you approve records from the Pending Approval page.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($documents->hasPages())
            <div class="pagination-wrap">{{ $documents->appends(request()->query())->links('vendor.pagination.papertrail') }}</div>
        @endif
    </section>
@endsection
