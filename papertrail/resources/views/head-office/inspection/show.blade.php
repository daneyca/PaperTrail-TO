@extends('layouts.dashboard')

@section('title', 'Inspection / Acceptance Detail | PaperTrail')

@section('content')
    @php $iarLocked = app(\App\Services\InspectionSignatureWorkflowService::class)->isLocked($record); @endphp
    @include('head-office.inspection.partials.signature-workflow')
    <link rel="stylesheet" href="{{ asset('css/inspection-acceptance.css') }}?v={{ filemtime(public_path('css/inspection-acceptance.css')) }}">
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
            <a href="{{ route('head-office.inspection.records') }}" class="dashboard-action secondary-action">Back to Inspection List</a>
            <a href="{{ route('head-office.inspection.print', $purchaseOrder) }}" target="_blank" rel="noopener" class="dashboard-action">Print Official IAR</a>
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
        :back-url="route('head-office.inspection.records')"
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
    <section class="budget-review-layout reviewed-detail-layout iar-review-layout">
        <article class="table-panel budget-detail-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Purchase Order</p>
                    <h2>{{ $purchaseOrder->po_number ?? 'Draft PO' }}</h2>
                    <p>SVP Inspection and Acceptance Report using the official Municipality of Tomas Oppus layout.</p>
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

        <aside class="table-panel budget-action-panel iar-controls-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Actions</p>
                    <h2>Inspection Controls</h2>
                    <p>Record inspection details, then mark the Purchase Order accepted or completed.</p>
                </div>
            </div>

            @if (auth()->user()->canManageSvpDocuments())
            @if (! $iarLocked)<form method="POST" action="{{ route('head-office.inspection.store', $purchaseOrder) }}" class="budget-action-form iar-control-form" aria-label="Inspection details">
                @csrf

                <label for="report_date">IAR Date</label>
                <input id="report_date" name="report_date" type="date" value="{{ old('report_date', $record->report_date?->format('Y-m-d') ?? now()->toDateString()) }}">
                @error('report_date')<span class="field-error">{{ $message }}</span>@enderror

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

                <label for="invoice_date">Invoice Date</label>
                <input id="invoice_date" name="invoice_date" type="date" value="{{ old('invoice_date', $record->invoice_date?->format('Y-m-d')) }}">
                @error('invoice_date')<span class="field-error">{{ $message }}</span>@enderror

                <label for="quantity_condition">Quantity Condition</label>
                <select id="quantity_condition" name="quantity_condition" required>
                    @foreach (['Complete', 'Partial'] as $condition)
                        <option value="{{ $condition }}" @selected(old('quantity_condition', $record->quantity_condition ?? 'Complete') === $condition)>{{ $condition }}</option>
                    @endforeach
                    @if ($record->quantity_condition && ! in_array($record->quantity_condition, ['Complete', 'Partial'], true))
                        <option value="{{ $record->quantity_condition }}" @selected(old('quantity_condition', $record->quantity_condition) === $record->quantity_condition)>{{ $record->quantity_condition }}</option>
                    @endif
                </select>
                @error('quantity_condition')<span class="field-error">{{ $message }}</span>@enderror

                <label for="quality_condition">Quality Condition</label>
                <input id="quality_condition" name="quality_condition" type="text" value="{{ old('quality_condition', $record->quality_condition ?? 'Conforming') }}" required>
                @error('quality_condition')<span class="field-error">{{ $message }}</span>@enderror

                <label for="inspector_name">Inspection Officer / Designee</label>
                <input id="inspector_name" name="inspector_name" type="text" maxlength="255" value="{{ old('inspector_name', $record->inspector_name) }}" placeholder="Enter Name">
                @error('inspector_name')<span class="field-error">{{ $message }}</span>@enderror

                <label for="inspector_designation">Inspection Officer Designation</label>
                <input id="inspector_designation" name="inspector_designation" type="text" maxlength="255" value="{{ old('inspector_designation', $record->inspector_designation ?? 'Inspection Officer - Des.') }}">
                @error('inspector_designation')<span class="field-error">{{ $message }}</span>@enderror

                <label for="inspection_chair_name">HRMO/Municipal Assessor Designate</label>
                <input id="inspection_chair_name" name="inspection_chair_name" type="text" maxlength="255" value="{{ old('inspection_chair_name', $record->inspection_chair_name) }}" placeholder="Enter Name">
                @error('inspection_chair_name')<span class="field-error">{{ $message }}</span>@enderror

                <label for="inspection_chair_designation">Inspection Chair Designation</label>
                <input id="inspection_chair_designation" name="inspection_chair_designation" type="text" maxlength="255" value="{{ old('inspection_chair_designation', $record->inspection_chair_designation ?? 'HRMO/Municipal Assessor Designate') }}">
                @error('inspection_chair_designation')<span class="field-error">{{ $message }}</span>@enderror

                <label for="acceptance_officer_name">Acceptance Officer</label>
                <input id="acceptance_officer_name" name="acceptance_officer_name" type="text" maxlength="255" value="{{ old('acceptance_officer_name', $record->acceptance_officer_name) }}" placeholder="Enter Name">
                @error('acceptance_officer_name')<span class="field-error">{{ $message }}</span>@enderror

                <label for="acceptance_officer_designation">Acceptance Officer Designation</label>
                <input id="acceptance_officer_designation" name="acceptance_officer_designation" type="text" maxlength="255" value="{{ old('acceptance_officer_designation', $record->acceptance_officer_designation ?? 'Admin Aide I') }}">
                @error('acceptance_officer_designation')<span class="field-error">{{ $message }}</span>@enderror

                <label for="findings">Inspection Findings</label>
                <textarea id="findings" name="findings" rows="4" placeholder="Record findings, defects, or compliance notes.">{{ old('findings', $record->findings) }}</textarea>
                @error('findings')<span class="field-error">{{ $message }}</span>@enderror

                <label for="remarks">Remarks</label>
                <textarea id="remarks" name="remarks" rows="3" placeholder="Optional remarks.">{{ old('remarks', $record->remarks) }}</textarea>
                @error('remarks')<span class="field-error">{{ $message }}</span>@enderror

                <button type="submit">Save Inspection Record</button>
            </form>@endif
            @endif

            @if ($canAccept)
                <form method="POST" action="{{ route('head-office.inspection.accept', $purchaseOrder) }}" class="budget-action-form iar-control-form" aria-label="Accept Purchase Order" onsubmit="return confirm('Mark this Purchase Order as accepted?');">
                    @csrf
                    @method('PATCH')
                    <h3>Acceptance</h3>
                    <label for="acceptance_date_action">Acceptance Date</label>
                    <input id="acceptance_date_action" name="acceptance_date" type="date" value="{{ now()->toDateString() }}" required>
                    <label for="acceptance_remarks">Acceptance Remarks</label>
                    <textarea id="acceptance_remarks" name="remarks" rows="3" placeholder="Optional acceptance remarks.">{{ old('remarks', $record->remarks) }}</textarea>
                    <button type="submit" class="success-action">Mark Accepted</button>
                </form>
            @endif

            @if ($canComplete)
                <form method="POST" action="{{ route('head-office.inspection.complete', $purchaseOrder) }}" class="budget-action-form iar-control-form" aria-label="Complete Purchase Order" onsubmit="return confirm('Mark this Purchase Order as completed?');">
                    @csrf
                    @method('PATCH')
                    <h3>Completion</h3>
                    <label for="completion_remarks">Completion Remarks</label>
                    <textarea id="completion_remarks" name="remarks" rows="3" placeholder="Optional completion remarks.">{{ old('remarks', $record->remarks) }}</textarea>
                    <button type="submit">Mark Completed</button>
                </form>
            @endif
        </aside>
    </section>

    <section class="table-panel">
        <div class="panel-heading">
            <div><p class="eyebrow">Official SVP Template</p><h2>Inspection and Acceptance Report</h2><p>Save inspection details to update this preview and the printed report.</p></div>
            <a href="{{ route('head-office.inspection.print', $purchaseOrder) }}" target="_blank" rel="noopener" class="dashboard-action secondary-action">Print Report</a>
        </div>
        <div class="iar-preview">@include('head-office.inspection.partials.report', ['record' => $latestRecord ?? new \App\Models\InspectionAcceptanceRecord()])</div>
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
