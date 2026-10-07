@extends('layouts.dashboard')

@section('title', 'Reviewed Documents | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
        $activeAccountingStatus = $filters['accounting_status'] ?? request('accounting_status');
        $activeDateFrom = $filters['date_from'] ?? request('date_from');
        $monthStart = now()->startOfMonth()->toDateString();
    @endphp

    <section class="dashboard-hero admin-users-hero budget-reviewed-header">
        <div>
            <p class="eyebrow">Accounting Office</p>
            <h1>Reviewed Documents</h1>
            <p>View procurement documents verified or endorsed by the Accounting Office.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern reviewed-summary-strip" aria-label="Accounting reviewed documents summary">
        <x-dashboard.stat-card href="{{ route('accounting.reviewed.index') }}" value="{{ $summary['totalReviewed'] }}" label="Total Reviewed" accent="navy" class="{{ blank($activeStatus) && blank($activeAccountingStatus) && blank($activeDateFrom) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('accounting.reviewed.index', ['status' => \App\Models\ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW]) }}" value="{{ $summary['forwardedToBac'] }}" label="Forwarded to BAC Secretariat" accent="blue" class="{{ $activeStatus === \App\Models\ProcurementDocument::STATUS_PENDING_BAC_SECRETARIAT_REVIEW ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('accounting.reviewed.index', ['accounting_status' => \App\Models\AccountingReview::STATUS_ACCOUNTING_VERIFIED]) }}" value="{{ $summary['accountingVerified'] }}" label="Accounting Verified" accent="green" class="{{ $activeAccountingStatus === \App\Models\AccountingReview::STATUS_ACCOUNTING_VERIFIED ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('accounting.reviewed.index', ['date_from' => $monthStart]) }}" value="{{ $summary['reviewedThisMonth'] }}" label="Reviewed This Month" accent="gold" class="{{ $activeDateFrom === $monthStart ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel" aria-label="Reviewed accounting documents">
        <form method="GET" action="{{ route('accounting.reviewed.index') }}" class="budget-filter-toolbar reviewed-filter-toolbar">
            <div class="user-search">
                <label for="accounting-reviewed-search">Search documents</label>
                <input id="accounting-reviewed-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or requesting office">
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
                <label for="accounting-status-filter">Accounting Status</label>
                <select id="accounting-status-filter" name="accounting_status">
                    <option value="">All accounting statuses</option>
                    @foreach ($accountingStatuses as $status)
                        <option value="{{ $status }}" @selected(($filters['accounting_status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="status-filter">Current Status</label>
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
                <a href="{{ route('accounting.reviewed.index') }}">Clear</a>
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
                        <th>Accounting Status</th>
                        <th>Current Status</th>
                        <th>Reviewed Date</th>
                        <th>Forwarded To</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $latestReview = $document->accountingReviews
                                ->sortByDesc(fn ($review) => $review->completed_at ?? $review->created_at)
                                ->first();
                            $reviewedDate = $document->accounting_reviewed_at ?? $latestReview?->completed_at;
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>
                                <strong>{{ $document->title }}</strong>
                            </td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td><span class="status-pill status-{{ $document->accounting_status }}">{{ str($document->accounting_status)->replace('_', ' ')->title() }}</span></td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ str($document->status)->replace('_', ' ')->title() }}</span></td>
                            <td class="nowrap">{{ $reviewedDate?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $document->currentOffice?->name ?? $document->assignedTo?->office ?? 'N/A' }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('accounting.reviewed.show', $document) }}">View Details</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <strong>No reviewed documents yet</strong>
                                    <p>Accounting-reviewed documents will appear here after verification is completed.</p>
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
