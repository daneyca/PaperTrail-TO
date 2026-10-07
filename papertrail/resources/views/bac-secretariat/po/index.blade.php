@extends('layouts.dashboard')

@section('title', 'Purchase Orders | PaperTrail')

@section('content')
    @php
        $activeStatus = $filters['status'] ?? request('status');
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>Purchase Orders</h1>
            <p>Prepare official Purchase Orders from approved procurement records.</p>
        </div>
        <a href="{{ route('bac-secretariat.purchase-orders.create') }}" class="dashboard-action">Create Purchase Order</a>
    </section>

    <section class="stat-grid stat-grid-modern" aria-label="Purchase Order summary">
        <x-dashboard.stat-card href="{{ route('bac-secretariat.purchase-orders.index', ['status' => 'draft']) }}" value="{{ $summary['draft'] }}" label="Draft" accent="gold" class="{{ $activeStatus === 'draft' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.purchase-orders.index', ['status' => 'submitted']) }}" value="{{ $summary['submitted'] }}" label="Submitted" accent="blue" class="{{ $activeStatus === 'submitted' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.purchase-orders.index', ['status' => 'fund_certified']) }}" value="{{ $summary['fund_certified'] }}" label="Fund Certified" accent="green" class="{{ $activeStatus === 'fund_certified' ? 'is-filter-active' : '' }}" />
        <x-dashboard.stat-card href="{{ route('bac-secretariat.purchase-orders.index', ['status' => 'approved']) }}" value="{{ $summary['approved'] }}" label="Approved" accent="green" class="{{ $activeStatus === 'approved' ? 'is-filter-active' : '' }}" />
    </section>

    <section class="table-panel" aria-label="Purchase Orders">
        <x-document-filter-card :action="route('bac-secretariat.purchase-orders.index')" class="app-filter-toolbar">
            <div class="user-search">
                <label for="po-search">Search</label>
                <input id="po-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="PO number, PR, supplier, office">
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
            <div class="filter-actions"><button type="submit">Apply</button><a href="{{ route('bac-secretariat.purchase-orders.index') }}">Clear</a></div>
        </x-document-filter-card>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>PO No.</th>
                        <th>Tracking Number</th>
                        <th>Supplier</th>
                        <th>Source PR</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Prepared By</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrders as $po)
                        <tr>
                            <td class="nowrap">{{ $po->po_number ?? 'Draft' }}</td>
                            <td class="nowrap">{{ $po->document_reference_number ?? 'Pending' }}</td>
                            <td>{{ $po->supplier_name ?? 'Not set' }}</td>
                            <td>{{ $po->sourcePrDocument?->tracking_number ?? 'Manual PO' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $po->total_amount, 2) }}</td>
                            <td><span class="status-pill status-{{ $po->status }}">{{ str($po->status)->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $po->preparedBy?->name ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $po->updated_at?->format('M d, Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-secretariat.purchase-orders.show', $po) }}">View</a>
                                    @if ($po->isEditable())
                                        <a href="{{ route('bac-secretariat.purchase-orders.edit', $po) }}">Continue Editing</a>
                                    @endif
                                    <a href="{{ route('bac-secretariat.purchase-orders.print', $po) }}" target="_blank">Print</a>
                                    @if ($po->canSubmit())
                                        <form method="POST" action="{{ route('bac-secretariat.purchase-orders.submit', $po) }}" onsubmit="return confirm('Submit this Purchase Order?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit">Submit</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty-state"><strong>No purchase orders yet</strong><p>Purchase Orders prepared by BAC Secretariat will appear here.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($purchaseOrders->hasPages())
            <div class="pagination-wrap">{{ $purchaseOrders->appends(request()->query())->links('vendor.pagination.papertrail') }}</div>
        @endif
    </section>
@endsection
