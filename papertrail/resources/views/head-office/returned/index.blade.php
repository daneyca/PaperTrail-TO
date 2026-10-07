@extends('layouts.dashboard')

@section('title', 'Returned Documents | PaperTrail')

@section('content')
    @php
        $officeName = $office?->name ?? auth()->user()->office ?? 'your office';
        $activeDocumentType = $filters['document_type'] ?? request('document_type');
        $activeDateFrom = $filters['date_from'] ?? request('date_from');
        $monthStart = now()->startOfMonth()->toDateString();
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Head of Office / End User</p>
            <h1>Returned Documents</h1>
            <p>View documents returned to {{ $officeName }} for correction.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern head-office-summary-grid" aria-label="Returned documents summary">
        <x-dashboard.stat-card href="{{ route('head-office.returned.index') }}" value="{{ $summary['total'] }}" label="Total Returned" accent="red" class="{{ blank($activeDocumentType) && blank($activeDateFrom) ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.returned.index', ['document_type' => 'PPMP']) }}" value="{{ $summary['ppmp'] }}" label="Returned PPMPs" accent="red" class="{{ $activeDocumentType === 'PPMP' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.returned.index', ['document_type' => 'pr']) }}" value="{{ $summary['pr'] }}" label="Returned PRs" accent="red" class="{{ $activeDocumentType === 'pr' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.returned.index', ['date_from' => $monthStart]) }}" value="{{ $summary['thisMonth'] }}" label="Returned This Month" accent="red" class="{{ $activeDateFrom === $monthStart ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel" aria-label="Returned office documents">
        <x-document-filter-card :action="route('head-office.returned.index')" class="returned-documents-filter-toolbar">
            <div class="user-search">
                <label for="returned-search">Search Returned Documents</label>
                <input id="returned-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tracking number, title, or description">
            </div>

            <div class="user-filter">
                <label for="document-type-filter">Document Type</label>
                <select id="document-type-filter" name="document_type">
                    <option value="">All types</option>
                    @foreach ($documentTypes as $type)
                        <option value="{{ $type }}" @selected(($filters['document_type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="fiscal-year-filter">Fiscal Year</label>
                <select id="fiscal-year-filter" name="fiscal_year">
                    <option value="">All years</option>
                    @foreach ($fiscalYears as $year)
                        <option value="{{ $year }}" @selected((string) ($filters['fiscal_year'] ?? '') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="return-source-filter">Returned By</label>
                <select id="return-source-filter" name="return_source">
                    <option value="">All sources</option>
                    @foreach ($returnSources as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['return_source'] ?? '') === $value)>{{ $label }}</option>
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
                <a href="{{ route('head-office.returned.index') }}">Clear</a>
            </div>
        </x-document-filter-card>

        <div class="table-scroll returned-documents-table-scroll">
            <table class="user-management-table returned-documents-table">
                <thead>
                    <tr>
                        <th>Tracking Number</th>
                        <th>Document Type</th>
                        <th>Title</th>
                        <th>Fiscal Year</th>
                        <th>Returned By</th>
                        <th>Return Reason</th>
                        <th>Current Status</th>
                        <th>Current Stage</th>
                        <th>Returned Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $returnMeta = $document->return_meta;
                            $canRevisePpmp = $document->document_type === 'PPMP'
                                && $document->status === 'returned_by_bac_secretariat'
                                && Route::has('head-office.ppmp.edit');
                            $canRevisePr = in_array($document->document_type, ['PR', 'Purchase Request'], true)
                                && in_array($document->status, ['returned_by_pr_numbering_staff', 'returned_by_bac_secretariat'], true)
                                && Route::has('head-office.pr.edit');
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $document->tracking_number ?? 'Draft' }}</td>
                            <td>{{ $document->document_type }}</td>
                            <td>
                                <strong>{{ $document->title ?? $document->purpose ?? 'Untitled document' }}</strong>
                            </td>
                            <td>{{ $document->fiscal_year ?? 'N/A' }}</td>
                            <td>{{ $returnMeta['returned_by'] ?? $returnMeta['source'] ?? 'N/A' }}</td>
                            <td>{{ str($returnMeta['reason'] ?? 'No return reason recorded.')->limit(90) }}</td>
                            <td><span class="status-pill status-{{ $document->status }}">{{ str($document->status)->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $document->stage ?? 'N/A' }}</td>
                            <td class="nowrap">{{ isset($returnMeta['date']) && $returnMeta['date'] ? $returnMeta['date']->format('M d, Y') : 'N/A' }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('head-office.returned.show', $document) }}">View Details</a>
                                    @if ($canRevisePpmp)
                                        <a href="{{ route('head-office.ppmp.edit', $document) }}">Revise PPMP</a>
                                    @elseif ($canRevisePr)
                                        <a href="{{ route('head-office.pr.edit', $document) }}">Revise PR</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <strong>No returned documents</strong>
                                    <p>Documents returned to your office for correction will appear here.</p>
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
