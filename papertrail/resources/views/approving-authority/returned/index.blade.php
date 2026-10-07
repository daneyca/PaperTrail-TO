@extends('layouts.dashboard')

@section('title', 'Returned Documents | PaperTrail')

@section('content')
    @php
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
        $activeReturnTarget = $filters['return_target'] ?? request('return_target');
        $activeDateFrom = $filters['date_from'] ?? request('date_from');
        $monthStart = now()->startOfMonth()->toDateString();
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">Head of the Procuring Entity</p>
            <h1>Returned Documents</h1>
            <p>View procurement documents returned by the Head of the Procuring Entity for correction or clarification.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern pt-smooth-enter" style="--pt-delay: 120ms" aria-label="Returned documents summary">
        <x-dashboard.stat-card href="{{ route('approving-authority.returned.index') }}" value="{{ $summary['total'] }}" label="Total Returned" accent="red" class="{{ blank($activeReturnTarget) && blank($activeDateFrom) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('approving-authority.returned.index', ['return_target' => \App\Models\DocumentApproval::DECISION_RETURNED_TO_BAC_CHAIR]) }}" value="{{ $summary['bacChair'] }}" label="Returned to BAC Chair" accent="red" class="{{ $activeReturnTarget === \App\Models\DocumentApproval::DECISION_RETURNED_TO_BAC_CHAIR ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('approving-authority.returned.index', ['return_target' => \App\Models\DocumentApproval::DECISION_RETURNED_TO_BAC_SECRETARIAT]) }}" value="{{ $summary['bacSecretariat'] }}" label="Returned to BAC Secretariat" accent="red" class="{{ $activeReturnTarget === \App\Models\DocumentApproval::DECISION_RETURNED_TO_BAC_SECRETARIAT ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('approving-authority.returned.index', ['date_from' => $monthStart]) }}" value="{{ $summary['thisMonth'] }}" label="Returned This Month" accent="red" class="{{ $activeDateFrom === $monthStart ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 220ms" aria-label="Returned documents">
        <form method="GET" action="{{ route('approving-authority.returned.index') }}" class="budget-filter-toolbar">
            <div class="user-search"><label for="returned-search">Search documents</label><input id="returned-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or requesting office"></div>
            <div class="user-filter"><label for="document-type">Document Type</label><select id="document-type" name="document_type"><option value="">All types</option>@foreach ($documentTypes as $type)<option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="fiscal-year">Fiscal Year</label><select id="fiscal-year" name="fiscal_year"><option value="">All years</option>@foreach ($fiscalYears as $year)<option value="{{ $year }}" @selected((string) ($filters['fiscal_year'] ?? '') === (string) $year)>{{ $year }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="target-filter">Return Target</label><select id="target-filter" name="return_target"><option value="">All targets</option>@foreach ($returnTargets as $value => $text)<option value="{{ $value }}" @selected(($filters['return_target'] ?? '') === $value)>{{ $text }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="status-filter">Current Status</label><select id="status-filter" name="status"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label($status) }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="date-from">Date From</label><input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="user-filter"><label for="date-to">Date To</label><input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="filter-actions"><button type="submit">Apply</button><a href="{{ route('approving-authority.returned.index') }}">Clear</a></div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead><tr><th>Tracking Number</th><th>Document Type</th><th>Title</th><th>Requesting Office</th><th>Total Amount</th><th>Return Target</th><th>Return Reason</th><th>Returned Date</th><th>Current Status</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td><strong>{{ $document->title }}</strong></td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td>{{ $returnTargets[$document->approval_decision] ?? 'Returned for Correction' }}</td>
                            <td>{{ str($document->approval_remarks ?? 'N/A')->limit(70) }}</td>
                            <td class="nowrap">{{ $document->returned_by_approving_authority_at?->format('M d, Y') ?? $document->updated_at?->format('M d, Y') ?? 'N/A' }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $label($document->status) }}</span></td>
                            <td><div class="table-actions"><a href="{{ route('approving-authority.returned.show', $document) }}">View Details</a></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="empty-state"><strong>No returned documents</strong><p>Documents returned by the Head of the Procuring Entity will appear here.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($documents->hasPages())
            <div class="pagination-wrap">{{ $documents->appends(request()->query())->links('vendor.pagination.papertrail') }}</div>
        @endif
    </section>
@endsection
