@extends('layouts.dashboard')

@section('title', 'Reviewed Documents | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
        $activeBudgetStatus = $filters['budget_status'] ?? request('budget_status');
        $activeDateFrom = $filters['date_from'] ?? request('date_from');
        $monthStart = now()->startOfMonth()->toDateString();
    @endphp

    <section class="dashboard-hero admin-users-hero budget-reviewed-header">
        <div>
            <p class="eyebrow">Budget Office</p>
            <h1>Reviewed Documents</h1>
            <p>View procurement documents already reviewed or endorsed by the Budget Office.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern reviewed-summary-strip" aria-label="Reviewed documents summary">
        <x-dashboard.stat-card href="{{ route('budget.reviewed.index') }}" value="{{ $summary['totalReviewed'] }}" label="Total Reviewed" accent="navy" class="{{ blank($activeStatus) && blank($activeBudgetStatus) && blank($activeDateFrom) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('budget.reviewed.index', ['status' => \App\Models\ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW]) }}" value="{{ $summary['forwardedToAccounting'] }}" label="Forwarded to Accounting" accent="blue" class="{{ $activeStatus === \App\Models\ProcurementDocument::STATUS_PENDING_ACCOUNTING_REVIEW ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('budget.reviewed.index', ['budget_status' => \App\Models\BudgetReview::STATUS_BUDGET_AVAILABLE]) }}" value="{{ $summary['budgetAvailable'] }}" label="Budget Available" accent="green" class="{{ $activeBudgetStatus === \App\Models\BudgetReview::STATUS_BUDGET_AVAILABLE ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('budget.reviewed.index', ['date_from' => $monthStart]) }}" value="{{ $summary['reviewedThisMonth'] }}" label="Reviewed This Month" accent="gold" class="{{ $activeDateFrom === $monthStart ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel" aria-label="Reviewed budget documents">
        <form method="GET" action="{{ route('budget.reviewed.index') }}" class="budget-filter-toolbar reviewed-filter-toolbar">
            <div class="user-search">
                <label for="reviewed-search">Search documents</label>
                <input id="reviewed-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or requesting office">
            </div>

            <div class="user-filter">
                <label for="document-type">Document Type</label>
                <select id="document-type" name="document_type">
                    <option value="">All types</option>
                    @foreach ($documentTypes as $type)
                        <option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="fiscal-year">Fiscal Year</label>
                <select id="fiscal-year" name="fiscal_year">
                    <option value="">All years</option>
                    @foreach ($fiscalYears as $year)
                        <option value="{{ $year }}" @selected((string) ($filters['fiscal_year'] ?? '') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="status-filter">Status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="date-from">Date From</label>
                <input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
            </div>

            <div class="user-filter">
                <label for="date-to">Date To</label>
                <input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('budget.reviewed.index') }}">Clear</a>
            </div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Document Type</th>
                        <th>Title</th>
                        <th>Requesting Office</th>
                        <th>Total Amount</th>
                        <th>Budget Status</th>
                        <th>Current Status</th>
                        <th>Reviewed Date</th>
                        <th>Forwarded To</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $latestReview = $document->budgetReviews
                                ->sortByDesc(fn ($review) => $review->completed_at ?? $review->created_at)
                                ->first();
                            $reviewedDate = $document->budget_reviewed_at ?? $latestReview?->completed_at;
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>
                                <strong>{{ $document->title }}</strong>
                            </td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td><span class="status-pill status-{{ $document->budget_status }}">{{ str($document->budget_status)->replace('_', ' ')->title() }}</span></td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ str($document->status)->replace('_', ' ')->title() }}</span></td>
                            <td class="nowrap">{{ $reviewedDate?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $document->currentOffice?->name ?? $document->assignedTo?->office ?? 'N/A' }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('budget.reviewed.show', $document) }}">View Details</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <strong>No reviewed documents yet</strong>
                                    <p>Budget-reviewed documents will appear here after review actions are completed.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($documents->hasPages())
            <div class="pagination-wrap">
                {{ $documents->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
