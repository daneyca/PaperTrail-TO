@extends('layouts.dashboard')

@section('title', 'Purchase Order Procurement Control | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero no-print">
        <div>
            <p class="eyebrow">Procurement Control</p>
            <h1>Assign PO Number and Date</h1>
            <p>{{ $purchaseOrder->document_reference_number }} &middot; {{ $purchaseOrder->sourcePrDocument?->submittingOffice?->name ?? 'Requesting Office' }}</p>
        </div>
        <div class="hero-actions"><a href="{{ route('pr-numbering.pending.index') }}" class="dashboard-action secondary-action">Back to Control Queue</a></div>
    </section>

    <section class="budget-review-layout pr-numbering-workspace no-print">
        <article class="table-panel budget-detail-panel">
            <div class="panel-heading"><div><p class="eyebrow">Purchase Order Details</p><h2>{{ $purchaseOrder->supplier_name ?: 'Purchase Order' }}</h2></div></div>
            <div class="detail-grid budget-detail-grid">
                <div><span>Tracking Number</span><strong>{{ $purchaseOrder->document_reference_number }}</strong></div>
                <div><span>Requesting Office</span><strong>{{ $purchaseOrder->sourcePrDocument?->submittingOffice?->name ?? 'N/A' }}</strong></div>
                <div><span>Prepared By</span><strong>{{ $purchaseOrder->preparedBy?->name ?? 'N/A' }}</strong></div>
                <div><span>Total Amount</span><strong>PHP {{ number_format((float) $purchaseOrder->total_amount, 2) }}</strong></div>
            </div>
        </article>

        <aside class="table-panel budget-action-panel pr-numbering-action-panel">
            <div class="pr-numbering-action-grid">
                <form method="POST" action="{{ route('pr-numbering.purchase-orders.assign', $purchaseOrder) }}" class="budget-action-form pr-numbering-action-card" onsubmit="return confirm('Assign these official PO details and return the Purchase Order to the requesting office?');">
                    @csrf
                    @method('PATCH')
                    <div class="pr-numbering-card-heading"><p class="eyebrow">Assign Details</p><h3>Official PO Information</h3></div>
                    <div class="pr-numbering-field-grid">
                        <div class="pr-numbering-field"><label for="po_number">PO Number</label><input id="po_number" name="po_number" type="text" value="{{ old('po_number', $purchaseOrder->po_number) }}" placeholder="Enter PO number" required>@error('po_number')<span class="field-error">{{ $message }}</span>@enderror</div>
                        <div class="pr-numbering-field"><label for="po_date">PO Date</label><input id="po_date" name="po_date" type="date" value="{{ old('po_date', $purchaseOrder->po_date?->format('Y-m-d')) }}" required>@error('po_date')<span class="field-error">{{ $message }}</span>@enderror</div>
                        <div class="pr-numbering-field is-wide"><label for="assign-remarks">Remarks</label><textarea id="assign-remarks" name="remarks" rows="3">{{ old('remarks') }}</textarea></div>
                    </div>
                    <button type="submit" class="dashboard-action">Assign and Return to Office</button>
                </form>

                <form method="POST" action="{{ route('pr-numbering.purchase-orders.return', $purchaseOrder) }}" class="budget-action-form pr-numbering-action-card pr-numbering-return-card" onsubmit="return confirm('Return this Purchase Order for correction?');">
                    @csrf
                    @method('PATCH')
                    <div class="pr-numbering-card-heading"><p class="eyebrow">Return PO</p><h3>Correction Required</h3></div>
                    <div class="pr-numbering-field"><label for="return-remarks">Return Reason</label><textarea id="return-remarks" name="remarks" rows="4" required>{{ old('remarks') }}</textarea></div>
                    <button type="submit" class="dashboard-action danger-action">Return for Correction</button>
                </form>
            </div>
        </aside>
    </section>

    @include('head-office.purchase-orders.partials.purchase-order-excel-form', ['po' => $purchaseOrder, 'sourceDocument' => $purchaseOrder->sourcePrDocument, 'mode' => 'show'])
@endsection
