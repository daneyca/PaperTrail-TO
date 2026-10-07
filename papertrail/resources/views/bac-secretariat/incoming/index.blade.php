@extends('layouts.dashboard')

@section('title', 'Incoming Documents | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>Incoming Documents</h1>
            <p>Receive and monitor procurement documents forwarded to the BAC Secretariat.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="BAC Secretariat incoming summary">
        <x-dashboard.stat-card href="{{ route('bac-secretariat.incoming.index', ['status' => 'incoming']) }}" value="{{ $summary['incoming'] }}" label="Incoming" accent="gold" class="{{ blank($activeStatus) || $activeStatus === 'incoming' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.incoming.index', ['status' => \App\Models\ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT]) }}" value="{{ $summary['received'] }}" label="Received" accent="blue" class="{{ $activeStatus === \App\Models\ProcurementDocument::STATUS_RECEIVED_BY_BAC_SECRETARIAT ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.incoming.index', ['status' => \App\Models\ProcurementDocument::STATUS_UNDER_BAC_SECRETARIAT_REVIEW]) }}" value="{{ $summary['underReview'] }}" label="Under Review" accent="blue" class="{{ $activeStatus === \App\Models\ProcurementDocument::STATUS_UNDER_BAC_SECRETARIAT_REVIEW ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.incoming.index', ['status' => \App\Models\ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING]) }}" value="{{ $summary['ready'] }}" label="Ready for Routing" accent="green" class="{{ $activeStatus === \App\Models\ProcurementDocument::STATUS_READY_FOR_DOCUMENT_ROUTING ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel" aria-label="Incoming BAC Secretariat documents">
        <x-document-filter-card :action="route('bac-secretariat.incoming.index')">
            <div class="user-search">
                <label for="bac-search">Search documents</label>
                <input id="bac-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or requesting office">
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
                    <option value="">All active statuses</option>
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
                <a href="{{ route('bac-secretariat.incoming.index') }}">Clear</a>
            </div>
        </x-document-filter-card>

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
                        <th>Current Status</th>
                        <th>Received Date</th>
                        <th>Pending Days</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $receivedDate = $document->bac_secretariat_received_at ?? $document->accounting_reviewed_at ?? $document->updated_at;
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>
                                <strong>{{ $document->title }}</strong>
                            </td>
                            <td>{{ $document->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $document->total_amount, 2) }}</td>
                            <td><span class="status-pill status-{{ $document->budget_status ?? 'pending' }}">{{ $document->budget_status ? str($document->budget_status)->replace('_', ' ')->title() : 'N/A' }}</span></td>
                            <td><span class="status-pill status-{{ $document->accounting_status ?? 'pending' }}">{{ $document->accounting_status ? str($document->accounting_status)->replace('_', ' ')->title() : 'N/A' }}</span></td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ str($document->status)->replace('_', ' ')->title() }}</span></td>
                            <td class="nowrap">{{ $receivedDate?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $receivedDate ? $receivedDate->diffInDays(now()) : 0 }}</td>
                            <td><div class="table-actions"><a href="{{ route('bac-secretariat.incoming.show', $document) }}">View / Process</a></div></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
                                <div class="empty-state">
                                    <strong>No incoming documents</strong>
                                    <p>Documents forwarded to the BAC Secretariat will appear here.</p>
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
