@extends('layouts.dashboard')

@section('title', 'Returned Documents | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
        $activeBudgetStatus = $filters['budget_status'] ?? request('budget_status');
        $activeDateFrom = $filters['date_from'] ?? request('date_from');
        $monthStart = now()->startOfMonth()->toDateString();
    @endphp

    <section class="dashboard-hero admin-users-hero budget-returned-header">
        <div>
            <p class="eyebrow">Budget Office</p>
            <h1>Returned Documents</h1>
            <p>View procurement documents returned by the Budget Office for correction or clarification.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern reviewed-summary-strip" aria-label="Returned documents summary">
        <x-dashboard.stat-card href="{{ route('budget.returned.index') }}" value="{{ $summary['totalReturned'] }}" label="Total Returned" accent="red" class="{{ blank($activeStatus) && blank($activeBudgetStatus) && blank($activeDateFrom) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('budget.returned.index', ['date_from' => $monthStart]) }}" value="{{ $summary['returnedThisMonth'] }}" label="Returned This Month" accent="red" class="{{ $activeDateFrom === $monthStart ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('budget.returned.index', ['budget_status' => \App\Models\BudgetReview::STATUS_INSUFFICIENT_FUNDS]) }}" value="{{ $summary['insufficientFunds'] }}" label="Insufficient Funds" accent="gold" class="{{ $activeBudgetStatus === \App\Models\BudgetReview::STATUS_INSUFFICIENT_FUNDS ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('budget.returned.index', ['status' => \App\Models\ProcurementDocument::STATUS_RETURNED_BY_BUDGET]) }}" value="{{ $summary['awaitingResubmission'] }}" label="Awaiting Resubmission" accent="green" class="{{ $activeStatus === \App\Models\ProcurementDocument::STATUS_RETURNED_BY_BUDGET ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel" aria-label="Returned budget documents">
        <form method="GET" action="{{ route('budget.returned.index') }}" class="budget-filter-toolbar returned-filter-toolbar">
            <div class="user-search">
                <label for="returned-search">Search documents</label>
                <input id="returned-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or requesting office">
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
                <label for="budget-status">Return Reason / Budget Status</label>
                <select id="budget-status" name="budget_status">
                    <option value="">All reasons</option>
                    @foreach ($budgetStatuses as $status)
                        <option value="{{ $status }}" @selected(($filters['budget_status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
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
                <a href="{{ route('budget.returned.index') }}">Clear</a>
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
                        <th>Return Reason</th>
                        <th>Returned Date</th>
                        <th>Returned To</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $latestReturnReview = $document->budgetReviews
                                ->filter(fn ($review) => in_array($review->review_status, ['returned', 'insufficient_funds'], true))
                                ->sortByDesc(fn ($review) => $review->completed_at ?? $review->created_at)
                                ->first();
                            $returnHistory = $document->routingHistories->first(fn ($history) => $history->status_to === 'returned_by_budget');
                            $returnedDate = $document->budget_reviewed_at ?? $latestReturnReview?->completed_at ?? $returnHistory?->action_at;
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>
                                <strong>{{ $document->title }}</strong>
                            </td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td>{{ $document->budget_remarks ?? $latestReturnReview?->remarks ?? $document->remarks ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $returnedDate?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $document->currentOffice?->name ?? $document->assignedTo?->office ?? 'N/A' }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ str($document->status)->replace('_', ' ')->title() }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('budget.returned.show', $document) }}">View Details</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <strong>No returned documents</strong>
                                    <p>There are currently no documents returned by the Budget Office.</p>
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
