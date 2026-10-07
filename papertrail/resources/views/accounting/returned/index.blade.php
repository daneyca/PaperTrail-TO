@extends('layouts.dashboard')

@section('title', 'Returned Documents | PaperTrail')

@section('content')
    @php
        $activeReturnTarget = $filters['return_target'] ?? request('return_target');
        $activeDateFrom = $filters['date_from'] ?? request('date_from');
        $monthStart = now()->startOfMonth()->toDateString();
    @endphp

    <section class="dashboard-hero admin-users-hero budget-returned-header">
        <div>
            <p class="eyebrow">Accounting Office</p>
            <h1>Returned Documents</h1>
            <p>View procurement documents returned by the Accounting Office for correction or clarification.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern reviewed-summary-strip" aria-label="Accounting returned documents summary">
        <x-dashboard.stat-card href="{{ route('accounting.returned.index') }}" value="{{ $summary['totalReturned'] }}" label="Total Returned" accent="red" class="{{ blank($activeReturnTarget) && blank($activeDateFrom) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('accounting.returned.index', ['date_from' => $monthStart]) }}" value="{{ $summary['returnedThisMonth'] }}" label="Returned This Month" accent="red" class="{{ $activeDateFrom === $monthStart ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('accounting.returned.index', ['return_target' => 'budget']) }}" value="{{ $summary['returnedToBudget'] }}" label="Returned to Budget" accent="red" class="{{ $activeReturnTarget === 'budget' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('accounting.returned.index', ['return_target' => 'requesting_office']) }}" value="{{ $summary['returnedToRequestingOffice'] }}" label="Returned to Requesting Office" accent="red" class="{{ $activeReturnTarget === 'requesting_office' ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel" aria-label="Returned accounting documents">
        <form method="GET" action="{{ route('accounting.returned.index') }}" class="budget-filter-toolbar returned-filter-toolbar">
            <div class="user-search">
                <label for="accounting-returned-search">Search documents</label>
                <input id="accounting-returned-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or requesting office">
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
                <label for="return-target">Return Target</label>
                <select id="return-target" name="return_target">
                    <option value="">All targets</option>
                    <option value="budget" @selected(($filters['return_target'] ?? '') === 'budget')>Budget Office</option>
                    <option value="requesting_office" @selected(($filters['return_target'] ?? '') === 'requesting_office')>Requesting Office</option>
                </select>
            </div>

            <div class="user-filter">
                <label for="accounting-status">Accounting Status</label>
                <select id="accounting-status" name="accounting_status">
                    <option value="">All statuses</option>
                    @foreach ($accountingStatuses as $status)
                        <option value="{{ $status }}" @selected(($filters['accounting_status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
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
                <a href="{{ route('accounting.returned.index') }}">Clear</a>
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
                        <th>Return Target</th>
                        <th>Return Reason</th>
                        <th>Returned Date</th>
                        <th>Current Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $latestReturnReview = $document->accountingReviews
                                ->filter(fn ($review) => in_array($review->review_status, ['returned', 'non_compliant'], true))
                                ->sortByDesc(fn ($review) => $review->completed_at ?? $review->created_at)
                                ->first();
                            $returnHistory = $document->routingHistories->first(fn ($history) => $history->status_to === 'returned_by_accounting');
                            $returnedDate = $document->accounting_reviewed_at ?? $latestReturnReview?->completed_at ?? $returnHistory?->action_at;
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>
                                <strong>{{ $document->title }}</strong>
                            </td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td>{{ $returnHistory?->toOffice?->name ?? $document->currentOffice?->name ?? 'N/A' }}</td>
                            <td>{{ $document->accounting_remarks ?? $latestReturnReview?->remarks ?? $returnHistory?->comments ?? $document->remarks ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $returnedDate?->format('M d, Y') ?? 'N/A' }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ str($document->status)->replace('_', ' ')->title() }}</span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('accounting.returned.show', $document) }}">View Details</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <strong>No returned documents</strong>
                                    <p>Documents returned by the Accounting Office will appear here.</p>
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
