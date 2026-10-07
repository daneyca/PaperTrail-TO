@extends('layouts.dashboard')

@section('title', 'Pending Accounting Review | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Accounting Office</p>
            <h1>Pending Accounting Review</h1>
            <p>Review procurement documents routed to the Accounting Office for verification and compliance checking.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="Accounting review summary">
        <x-dashboard.stat-card href="{{ route('accounting.pending-review.index') }}" value="{{ $summary['pending'] }}" label="Pending Review" accent="gold" class="{{ blank($activeStatus) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('accounting.pending-review.index', ['status' => \App\Models\ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW]) }}" value="{{ $summary['underReview'] }}" label="Under Review" accent="blue" class="{{ $activeStatus === \App\Models\ProcurementDocument::STATUS_UNDER_ACCOUNTING_REVIEW ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('accounting.returned.index') }}" value="{{ $summary['returned'] }}" label="Returned by Accounting" accent="red" />
        <x-dashboard.stat-card href="{{ route('accounting.reviewed.index') }}" value="{{ $summary['reviewed'] }}" label="Accounting Reviewed" accent="green" />
    </section>

    <section class="table-panel" aria-label="Pending accounting review documents">
        <form method="GET" action="{{ route('accounting.pending-review.index') }}" class="budget-filter-toolbar">
            <div class="user-search">
                <label for="accounting-search">Search documents</label>
                <input id="accounting-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or requesting office">
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
                <label for="priority-filter">Priority</label>
                <select id="priority-filter" name="priority">
                    <option value="">All priorities</option>
                    @foreach (['normal' => 'Normal', 'urgent' => 'Urgent', 'high' => 'High'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="status-filter">Status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    <option value="pending_accounting_review" @selected(($filters['status'] ?? '') === 'pending_accounting_review')>Pending Accounting Review</option>
                    <option value="under_accounting_review" @selected(($filters['status'] ?? '') === 'under_accounting_review')>Under Accounting Review</option>
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
                <a href="{{ route('accounting.pending-review.index') }}">Clear</a>
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
                        <th>Accounting Status</th>
                        <th>Submitted / Received Date</th>
                        <th>Pending Days</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>
                                <strong>{{ $document->title }}</strong>
                            </td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td><span class="status-pill status-{{ $document->budget_status ?? 'pending' }}">{{ $document->budget_status ? str($document->budget_status)->replace('_', ' ')->title() : 'N/A' }}</span></td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ str($document->accounting_status ?? $document->status)->replace('_', ' ')->title() }}</span></td>
                            <td class="nowrap">{{ ($document->updated_at ?? $document->submitted_at)?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ ($document->updated_at ?? $document->submitted_at) ? ($document->updated_at ?? $document->submitted_at)->diffInDays(now()) : 0 }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('accounting.pending-review.show', $document) }}">View / Review</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <strong>No pending accounting reviews</strong>
                                    <p>Documents forwarded by the Budget Office will appear here for accounting verification.</p>
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
