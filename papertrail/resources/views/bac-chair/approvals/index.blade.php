@extends('layouts.dashboard')

@section('title', 'BAC Chair Approvals | PaperTrail')

@section('content')
    @php
        $label = fn ($value) => $value ? str($value)->replace('_', ' ')->title() : 'N/A';
        $latestMemberReview = fn ($document) => $document->bacMemberReviews->sortByDesc(fn ($review) => $review->completed_at ?? $review->created_at)->first();
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero pt-smooth-enter" style="--pt-delay: 0ms">
        <div>
            <p class="eyebrow">BAC Chair</p>
            <h1>BAC Approvals</h1>
            <p>Review BAC-endorsed procurement documents and forward confirmed records to the Head of the Procuring Entity.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern pt-smooth-enter" style="--pt-delay: 120ms" aria-label="BAC Chair approval summary">
        <x-dashboard.stat-card href="{{ route('bac-chair.approvals.index') }}" value="{{ $summary['pending'] }}" label="Pending BAC Approval" accent="gold" class="{{ blank($activeStatus) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-chair.approvals.index', ['status' => \App\Models\ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW]) }}" value="{{ $summary['underReview'] }}" label="Under Review" accent="blue" class="{{ $activeStatus === \App\Models\ProcurementDocument::STATUS_UNDER_BAC_CHAIR_REVIEW ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-chair.reviewed.index', ['outcome' => 'forwarded']) }}" value="{{ $summary['confirmed'] }}" label="Confirmed" accent="green" />
        <x-dashboard.stat-card href="{{ route('bac-chair.reviewed.index', ['outcome' => 'returned']) }}" value="{{ $summary['returned'] }}" label="Returned" accent="red" />
    </section>

    <section class="table-panel pt-smooth-enter" style="--pt-delay: 220ms" aria-label="BAC Chair approval queue">
        <form method="GET" action="{{ route('bac-chair.approvals.index') }}" class="budget-filter-toolbar">
            <div class="user-search">
                <label for="approval-search">Search documents</label>
                <input id="approval-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or requesting office">
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
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label($status) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="priority-filter">Priority</label>
                <select id="priority-filter" name="priority">
                    <option value="">All priorities</option>
                    @foreach (['normal' => 'Normal', 'urgent' => 'Urgent', 'high' => 'High', 'low' => 'Low'] as $value => $text)
                        <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $text }}</option>
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
                <a href="{{ route('bac-chair.approvals.index') }}">Clear</a>
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
                        <th>BAC Member Recommendation</th>
                        <th>Current Status</th>
                        <th>Current Stage</th>
                        <th>Assigned Date</th>
                        <th>Pending Days</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $assignedDate = $document->routed_at ?? $document->updated_at;
                            $memberReview = $latestMemberReview($document);
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>
                                <strong>{{ $document->title }}</strong>
                            </td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td>{{ $label($memberReview?->recommendation) }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ $label($document->status) }}</span></td>
                            <td>{{ $document->stage ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $assignedDate?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $assignedDate ? $assignedDate->diffInDays(now()) : 0 }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-chair.approvals.show', $document) }}">View / Review</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
                                <div class="empty-state">
                                    <strong>No BAC approvals pending</strong>
                                    <p>Documents endorsed for BAC Chair review will appear here.</p>
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
