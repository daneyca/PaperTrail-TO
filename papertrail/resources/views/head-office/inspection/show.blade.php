@extends('layouts.dashboard')

@section('title', 'Inspection / Acceptance Detail | PaperTrail')

@section('content')
    @php
        $statusLabel = fn (?string $status) => $status ? str($status)->replace('_', ' ')->title() : 'Pending';
        $latestRecord = $records->first();
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Head Office / End User</p>
            <h1>Inspection / Acceptance</h1>
            <p>{{ $purchaseOrder->po_number ?? 'Draft PO' }} · {{ $purchaseOrder->supplier_name ?? 'No supplier recorded' }}</p>
        </div>

        <div class="hero-actions no-print">
            <a href="{{ route('head-office.inspection.index') }}" class="dashboard-action secondary-action">Back to Inspection List</a>
            @if ($latestRecord)
                <x-ai.completeness-check-button
                    document-type="inspection_acceptance"
                    :document-id="$latestRecord->id"
                    :tracking-number="$purchaseOrder->po_number ?? ('Inspection #' . $latestRecord->id)"
                    label="AI Check Inspection"
                />
            @else
                <x-ai.completeness-check-button
                    document-type="purchase_order"
                    :document-id="$purchaseOrder->id"
                    :tracking-number="$purchaseOrder->po_number"
                    label="AI Check PO"
                />
            @endif
        </div>
    </section>

    <x-documents.document-create-flow
        eyebrow="Inspection / Acceptance"
        title="Review Purchase Order Source"
        description="Confirm the Purchase Order details before opening inspection and acceptance controls."
        proceed-label="Proceed to Inspection Controls"
        :back-url="route('head-office.inspection.index')"
        back-label="Back to Inspection List"
        :autoshow="$errors->any()"
        :workflow-steps="['Select Purchase Order', 'Review Items', 'Inspection Result', 'Acceptance']"
        :form-step="3"
    >
        <x-slot:source>
            <div class="detail-grid metadata-grid">
                <div><span>Tracking Number</span><strong>{{ $latestRecord?->document_reference_number ?? 'Pending' }}</strong></div>
                <div><span>PO Tracking Number</span><strong>{{ $purchaseOrder->document_reference_number ?? 'Pending' }}</strong></div>
                <div><span>PO Number</span><strong>{{ $purchaseOrder->po_number ?? 'Draft' }}</strong></div>
                <div><span>Source PR</span><strong>{{ $purchaseOrder->sourcePrDocument?->pr_no ?? $purchaseOrder->sourcePrDocument?->tracking_number ?? 'N/A' }}</strong></div>
                <div><span>Requesting Office</span><strong>{{ $purchaseOrder->sourcePrDocument?->submittingOffice?->name ?? 'N/A' }}</strong></div>
                <div><span>Supplier</span><strong>{{ $purchaseOrder->supplier_name ?? 'N/A' }}</strong></div>
                <div><span>Total Amount</span><strong>PHP {{ number_format((float) $purchaseOrder->total_amount, 2) }}</strong></div>
                <div><span>Inspection Status</span><strong>{{ $statusLabel($latestRecord?->status) }}</strong></div>
            </div>
        </x-slot:source>

        <x-slot:form>
    <section class="budget-review-layout reviewed-detail-layout">
        <article class="table-panel budget-detail-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Purchase Order</p>
                    <h2>{{ $purchaseOrder->po_number ?? 'Draft PO' }}</h2>
                    <p>Inspection and acceptance records are based on the issued Purchase Order, not a final official inspection form template.</p>
                </div>
                <span class="status-pill status-{{ $purchaseOrder->status }}">{{ $statusLabel($purchaseOrder->status) }}</span>
            </div>

            <div class="detail-grid budget-detail-grid">
                <div><span>Tracking Number</span><strong>{{ $latestRecord?->document_reference_number ?? 'Pending' }}</strong></div>
                <div><span>PO Tracking Number</span><strong>{{ $purchaseOrder->document_reference_number ?? 'Pending' }}</strong></div>
                <div><span>PO Number</span><strong>{{ $purchaseOrder->po_number ?? 'Draft' }}</strong></div>
                <div><span>Source PR</span><strong>{{ $purchaseOrder->sourcePrDocument?->pr_no ?? $purchaseOrder->sourcePrDocument?->tracking_number ?? 'N/A' }}</strong></div>
                <div><span>Requesting Office</span><strong>{{ $purchaseOrder->sourcePrDocument?->submittingOffice?->name ?? 'N/A' }}</strong></div>
                <div><span>Supplier</span><strong>{{ $purchaseOrder->supplier_name ?? 'N/A' }}</strong></div>
                <div><span>Total Amount</span><strong>PHP {{ number_format((float) $purchaseOrder->total_amount, 2) }}</strong></div>
                <div><span>Delivery Place</span><strong>{{ $purchaseOrder->place_of_delivery ?? $purchaseOrder->delivery_place ?? 'N/A' }}</strong></div>
                <div><span>Delivery Date</span><strong>{{ $purchaseOrder->delivery_date?->format('M d, Y') ?? $purchaseOrder->date_of_delivery ?? 'N/A' }}</strong></div>
                <div><span>Prepared By</span><strong>{{ $purchaseOrder->preparedBy?->name ?? 'N/A' }}</strong></div>
                <div><span>Inspection Status</span><strong>{{ $statusLabel($latestRecord?->status) }}</strong></div>
                <div><span>Inspection Date</span><strong>{{ $latestRecord?->inspection_date?->format('M d, Y') ?? 'Not recorded' }}</strong></div>
                <div><span>Acceptance Date</span><strong>{{ $latestRecord?->acceptance_date?->format('M d, Y') ?? 'Not recorded' }}</strong></div>
                <div><span>Accepted By</span><strong>{{ $latestRecord?->acceptedBy?->name ?? 'N/A' }}</strong></div>
            </div>
        </article>

        <aside class="table-panel budget-action-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Actions</p>
                    <h2>Inspection Controls</h2>
                    <p>Record inspection details, then mark the Purchase Order accepted or completed.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('head-office.inspection.store', $purchaseOrder) }}" class="budget-action-form">
                @csrf

                <label for="inspection_date">Inspection Date</label>
                <input id="inspection_date" name="inspection_date" type="date" value="{{ old('inspection_date', optional($record->inspection_date)->format('Y-m-d') ?? now()->toDateString()) }}" required>
                @error('inspection_date')<span class="field-error">{{ $message }}</span>@enderror

                <label for="acceptance_date">Acceptance Date</label>
                <input id="acceptance_date" name="acceptance_date" type="date" value="{{ old('acceptance_date', optional($record->acceptance_date)->format('Y-m-d')) }}">
                @error('acceptance_date')<span class="field-error">{{ $message }}</span>@enderror

                <label for="delivery_receipt_number">Delivery Receipt No.</label>
                <input id="delivery_receipt_number" name="delivery_receipt_number" type="text" value="{{ old('delivery_receipt_number', $record->delivery_receipt_number) }}">
                @error('delivery_receipt_number')<span class="field-error">{{ $message }}</span>@enderror

                <label for="invoice_number">Invoice No.</label>
                <input id="invoice_number" name="invoice_number" type="text" value="{{ old('invoice_number', $record->invoice_number) }}">
                @error('invoice_number')<span class="field-error">{{ $message }}</span>@enderror

                <label for="quantity_condition">Quantity Condition</label>
                <input id="quantity_condition" name="quantity_condition" type="text" value="{{ old('quantity_condition', $record->quantity_condition ?? 'Complete') }}" required>
                @error('quantity_condition')<span class="field-error">{{ $message }}</span>@enderror

                <label for="quality_condition">Quality Condition</label>
                <input id="quality_condition" name="quality_condition" type="text" value="{{ old('quality_condition', $record->quality_condition ?? 'Conforming') }}" required>
                @error('quality_condition')<span class="field-error">{{ $message }}</span>@enderror

                <label for="findings">Inspection Findings</label>
                <textarea id="findings" name="findings" rows="4" placeholder="Record findings, defects, or compliance notes.">{{ old('findings', $record->findings) }}</textarea>
                @error('findings')<span class="field-error">{{ $message }}</span>@enderror

                <label for="remarks">Remarks</label>
                <textarea id="remarks" name="remarks" rows="3" placeholder="Optional remarks.">{{ old('remarks', $record->remarks) }}</textarea>
                @error('remarks')<span class="field-error">{{ $message }}</span>@enderror

                <button type="submit">Save Inspection Record</button>
            </form>

            @if ($canAccept)
                <form method="POST" action="{{ route('head-office.inspection.accept', $purchaseOrder) }}" class="budget-action-form" onsubmit="return confirm('Mark this Purchase Order as accepted?');">
                    @csrf
                    @method('PATCH')
                    <label for="acceptance_date_action">Acceptance Date</label>
                    <input id="acceptance_date_action" name="acceptance_date" type="date" value="{{ now()->toDateString() }}" required>
                    <label for="acceptance_remarks">Acceptance Remarks</label>
                    <textarea id="acceptance_remarks" name="remarks" rows="3" placeholder="Optional acceptance remarks.">{{ old('remarks', $record->remarks) }}</textarea>
                    <button type="submit" class="success-action">Mark Accepted</button>
                </form>
            @endif

            @if ($canComplete)
                <form method="POST" action="{{ route('head-office.inspection.complete', $purchaseOrder) }}" class="budget-action-form" onsubmit="return confirm('Mark this Purchase Order as completed?');">
                    @csrf
                    @method('PATCH')
                    <label for="completion_remarks">Completion Remarks</label>
                    <textarea id="completion_remarks" name="remarks" rows="3" placeholder="Optional completion remarks.">{{ old('remarks', $record->remarks) }}</textarea>
                    <button type="submit">Mark Completed</button>
                </form>
            @endif
        </aside>
    </section>

    @include('partials.svp-related-documents', ['source' => $latestRecord ?: $purchaseOrder, 'context' => 'head-office'])

    <x-documents.attachments-panel
        :document="$latestRecord ?: $purchaseOrder"
        :document-type="$latestRecord ? 'inspection_acceptance' : 'purchase_order'"
        :can-upload="$latestRecord ? in_array($latestRecord->status, ['draft', 'inspected'], true) : false"
        title="Inspection / Acceptance Attachments"
    />

    <section class="table-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Items</p>
                <h2>Purchase Order Items</h2>
                <p>Read-only item summary from the Purchase Order.</p>
            </div>
        </div>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Item No.</th>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Unit Cost</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrder->items as $item)
                        <tr>
                            <td>{{ $item->item_no ?? '-' }}</td>
                            <td>{{ $item->description ?? $item->item_description }}</td>
                            <td>{{ number_format((float) $item->quantity, 2) }}</td>
                            <td>{{ $item->unit ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $item->unit_cost, 2) }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $item->total_cost, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <strong>No items recorded</strong>
                                    <p>Purchase Order items will appear here when encoded.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">History</p>
                <h2>Inspection / Acceptance Records</h2>
            </div>
        </div>

        <div class="table-scroll">
            <table class="user-management-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Inspection Date</th>
                        <th>Acceptance Date</th>
                        <th>Inspected By</th>
                        <th>Accepted By</th>
                        <th>Remarks</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $history)
                        <tr>
                            <td><span class="status-pill status-{{ $history->status }}">{{ $statusLabel($history->status) }}</span></td>
                            <td>{{ $history->inspection_date?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $history->acceptance_date?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>{{ $history->inspectedBy?->name ?? 'N/A' }}</td>
                            <td>{{ $history->acceptedBy?->name ?? 'N/A' }}</td>
                            <td>{{ $history->remarks ?? $history->findings ?? 'N/A' }}</td>
                            <td class="nowrap">{{ $history->updated_at?->format('M d, Y h:i A') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <strong>No inspection record yet</strong>
                                    <p>Save inspection details to create the first record.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
        </x-slot:form>
    </x-documents.document-create-flow>
@endsection
