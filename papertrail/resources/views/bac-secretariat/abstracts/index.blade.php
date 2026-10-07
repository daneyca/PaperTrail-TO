@extends('layouts.dashboard')

@section('title', 'Abstract | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>Abstract</h1>
            <p>Prepare Abstract of Quotations/Canvass documents after RFQ and BAC Resolution.</p>
        </div>
        <a href="{{ route('bac-secretariat.abstracts.create') }}" class="dashboard-action">Create Abstract</a>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="Abstract summary">
        <x-dashboard.stat-card href="{{ route('bac-secretariat.abstracts.index', ['status' => 'draft']) }}" value="{{ $summary['draft'] }}" label="Draft" accent="gold" class="{{ $activeStatus === 'draft' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.abstracts.index', ['status' => 'submitted']) }}" value="{{ $summary['submitted'] }}" label="Submitted" accent="blue" class="{{ $activeStatus === 'submitted' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.abstracts.index', ['status' => 'ready_for_po']) }}" value="{{ $summary['ready_for_po'] }}" label="Ready for PO" accent="green" class="{{ $activeStatus === 'ready_for_po' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.abstracts.index', ['status' => 'returned']) }}" value="{{ $summary['returned'] }}" label="Returned" accent="red" class="{{ $activeStatus === 'returned' ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel" aria-label="Abstracts">
        <x-document-filter-card :action="route('bac-secretariat.abstracts.index')">
            <div class="user-search">
                <label for="abstract-search">Search Abstracts</label>
                <input id="abstract-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Abstract no., project, office, supplier">
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
            <div class="user-filter"><label for="date-from">Date From</label><input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="user-filter"><label for="date-to">Date To</label><input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="filter-actions"><button type="submit">Apply</button><a href="{{ route('bac-secretariat.abstracts.index') }}">Clear</a></div>
        </x-document-filter-card>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Abstract No.</th>
                        <th>Tracking Number</th>
                        <th>Project Name</th>
                        <th>Implementing Office</th>
                        <th>ABC</th>
                        <th>Lowest Supplier</th>
                        <th>Lowest Total Amount</th>
                        <th>Status</th>
                        <th>Prepared By</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($abstracts as $abstract)
                        <tr>
                            <td class="nowrap">{{ $abstract->abstract_number ?? 'Draft' }}</td>
                            <td class="nowrap">{{ $abstract->document_reference_number ?? 'Pending' }}</td>
                            <td>{{ str($abstract->project_name ?? 'Untitled Abstract')->limit(72) }}</td>
                            <td>{{ $abstract->implementing_office ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $abstract->abc_amount, 2) }}</td>
                            <td>{{ $abstract->lowest_supplier_name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $abstract->lowest_total_amount, 2) }}</td>
                            <td><span class="status-pill status-{{ $abstract->status }}">{{ str($abstract->status)->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $abstract->preparedBy?->name ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $abstract->updated_at?->format('M d, Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-secretariat.abstracts.show', $abstract) }}">View</a>
                                    @if ($abstract->isEditable())
                                        <a href="{{ route('bac-secretariat.abstracts.edit', $abstract) }}">Continue Editing</a>
                                    @endif
                                    <a href="{{ route('bac-secretariat.abstracts.print', $abstract) }}" target="_blank">Print</a>
                                    @if ($abstract->canSubmit())
                                        <form method="POST" action="{{ route('bac-secretariat.abstracts.submit', $abstract) }}" onsubmit="return confirm('Submit this Abstract?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit">Submit</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><div class="empty-state"><strong>No Abstracts yet</strong><p>Abstracts prepared by BAC Secretariat will appear here.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($abstracts->hasPages())
            <div class="pagination-wrap">{{ $abstracts->appends(request()->query())->links('vendor.pagination.papertrail') }}</div>
        @endif
    </section>
@endsection
