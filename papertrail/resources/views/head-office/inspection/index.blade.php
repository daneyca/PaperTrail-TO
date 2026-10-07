@extends('layouts.dashboard')

@section('title', 'Inspection / Acceptance | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Head Office / End User</p>
            <h1>Inspection / Acceptance</h1>
            <p>Monitor office Purchase Orders that are ready for inspection, acceptance, or completion records.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern head-office-summary-grid" aria-label="Inspection summary">
        <x-dashboard.stat-card href="{{ route('head-office.inspection.index', ['status' => 'draft']) }}" value="{{ $summary['draft'] }}" label="Inspection Drafts" accent="gold" class="{{ $activeStatus === 'draft' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.inspection.index', ['status' => 'for_inspection']) }}" value="{{ $summary['forInspection'] }}" label="For Inspection" accent="blue" class="{{ $activeStatus === 'for_inspection' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.inspection.index', ['status' => 'accepted']) }}" value="{{ $summary['accepted'] }}" label="Accepted / Completed" accent="green" class="{{ $activeStatus === 'accepted' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('head-office.inspection.index', ['status' => 'completed']) }}" value="{{ $summary['completed'] }}" label="Completed" accent="green" class="{{ $activeStatus === 'completed' ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel" aria-label="Inspection and acceptance records">
        <x-document-filter-card :action="route('head-office.inspection.index')">
            <div class="user-search">
                <label for="inspection-search">Search Purchase Orders</label>
                <input id="inspection-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="PO no., supplier, PR, or title">
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
            <div class="filter-actions"><button type="submit">Apply</button><a href="{{ route('head-office.inspection.index') }}">Clear</a></div>
        </x-document-filter-card>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>PO No.</th>
                        <th>Source PR</th>
                        <th>Tracking Number</th>
                        <th>Supplier</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Inspection Status</th>
                        <th>Prepared By</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrders as $po)
                        <tr>
                            <td class="nowrap">{{ $po->po_number ?? 'Draft' }}</td>
                            <td class="nowrap">{{ $po->sourcePrDocument?->pr_no ?? $po->sourcePrDocument?->tracking_number ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $po->latestInspectionAcceptanceRecord?->document_reference_number ?? 'Pending' }}</td>
                            <td>{{ $po->supplier_name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $po->total_amount, 2) }}</td>
                            <td><span class="status-pill status-{{ $po->status }}">{{ str($po->status)->replace('_', ' ')->title() }}</span></td>
                            <td>
                                @php $inspectionStatus = $po->latestInspectionAcceptanceRecord?->status; @endphp
                                <span class="status-pill status-{{ $inspectionStatus ?: 'pending' }}">
                                    {{ $inspectionStatus ? str($inspectionStatus)->replace('_', ' ')->title() : 'Pending' }}
                                </span>
                            </td>
                            <td>{{ $po->preparedBy?->name ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $po->updated_at?->format('M d, Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('head-office.inspection.show', $po) }}">Inspect</a>
                                    <a href="{{ route('head-office.purchase-orders.show', $po) }}">View PO</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <strong>No inspection records yet</strong>
                                    <p>Office Purchase Orders ready for inspection or acceptance will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($purchaseOrders->hasPages())
            <div class="pagination-wrap">{{ $purchaseOrders->appends(request()->query())->links('vendor.pagination.papertrail') }}</div>
        @endif
    </section>
@endsection
