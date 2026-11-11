@extends('layouts.dashboard')

@section('title', 'Create Inspection / Acceptance | PaperTrail')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/inspection-acceptance.css') }}?v={{ filemtime(public_path('css/inspection-acceptance.css')) }}">
    @php $inspectionDrafts = collect($recentDrafts ?? []); @endphp

    <x-documents.document-create-flow
        eyebrow="Inspection / Acceptance Creation"
        title="Create Inspection / Acceptance"
        description=""
        proceed-label="Proceed to IAR Form"
        :back-url="route('head-office.inspection.records')"
        back-label="View Inspection Records"
        :autoshow="$errors->any() || request()->boolean('edit')"
        :workflow-steps="['Select Source', 'Review Delivery Details', 'Prepare Inspection / Acceptance', 'Submit']"
        :form-step="3"
    >
        <x-slot:source>
            <div class="po-create-split-layout">
                <aside class="po-create-drafts-pane" aria-labelledby="recent-iar-drafts-title">
                    <header class="po-create-pane-heading">
                        <div><p class="eyebrow">Recent Drafts</p><h3 id="recent-iar-drafts-title">Recent IAR Drafts</h3></div>
                        <span>{{ $inspectionDrafts->count() }}</span>
                    </header>
                    <div class="po-create-draft-list">
                        @forelse ($inspectionDrafts as $draft)
                            <article class="po-create-draft-card po-create-draft-card--compact">
                                <strong class="po-create-draft-number">{{ $draft['number'] ?? 'Draft' }}</strong>
                                <span class="document-create-draft-status">{{ $draft['status_label'] ?? 'Draft' }}</span>
                                <div class="document-create-draft-icon-actions">
                                    @if ($draft['edit_url'] ?? null)
                                        <a href="{{ $draft['edit_url'] }}" class="po-create-draft-icon-action" aria-label="Edit draft" title="Edit draft"><x-papertrail.icon name="edit" /></a>
                                    @endif
                                    @if ($draft['show_url'] ?? null)
                                        <a href="{{ $draft['show_url'] }}" class="po-create-draft-icon-action" aria-label="View draft" title="View draft"><x-papertrail.icon name="view" /></a>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="po-create-drafts-empty"><strong>No draft Inspection / Acceptance reports yet.</strong></div>
                        @endforelse
                    </div>
                </aside>
                <section class="po-create-source-pane" aria-labelledby="iar-source-picker-title">
                    <header class="po-create-pane-heading"><div><p class="eyebrow">Source</p><h3 id="iar-source-picker-title">Select Inspection / Acceptance Source</h3></div></header>
                    <form method="GET" action="{{ route('head-office.inspection.index') }}" class="po-source-picker po-source-picker--split no-print">
                        <label for="purchase_order_id">Source Purchase Order
                            <select id="purchase_order_id" name="purchase_order_id" onchange="this.form.submit()">
                                <option value="">Blank Inspection / Acceptance Template</option>
                                @foreach ($eligiblePurchaseOrders as $source)
                                    <option value="{{ $source->id }}" @selected($purchaseOrder?->id === $source->id)>{{ $source->po_number ?? $source->document_reference_number }} - {{ $source->supplier_name ?? 'No supplier' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Requisitioning Office<input type="text" value="{{ $purchaseOrder?->sourcePrDocument?->submittingOffice?->name ?? '' }}" placeholder="From the selected Purchase Order" readonly></label>
                        <noscript><button type="submit">Use Source</button></noscript>
                    </form>
                    @if ($eligiblePurchaseOrders->isEmpty())
                        <p class="iar-source-hint">You can open and print the blank IAR template. Select a Purchase Order when you are ready to save an inspection record.</p>
                        <a href="{{ route('head-office.purchase-orders.create') }}" class="document-create-secondary-action">Create Purchase Order</a>
                    @endif
                </section>
            </div>
        </x-slot:source>

        <x-slot:form>
            @if ($purchaseOrder)
                @php $iarLocked = app(\App\Services\InspectionSignatureWorkflowService::class)->isLocked($record); @endphp
                @include('head-office.inspection.partials.signature-workflow')
                <form method="POST" action="{{ route('head-office.inspection.store', $purchaseOrder) }}" class="po-workspace iar-editor-form">
                    @csrf
                    <input type="hidden" name="return_to" value="editor">
                    <section class="po-toolbar no-print">
                        <div><strong>Inspection and Acceptance Report</strong><span>Edit directly inside the official LGU Tomas Oppus form.</span></div>
                        <a href="{{ route('head-office.inspection.index') }}">Change Source</a>
                        @if (! $iarLocked)
                            <button type="submit" name="save_action" value="draft">Save Draft</button>
                            <button type="submit" name="save_action" value="submit">Submit for Signatures</button>
                        @endif
                        @if ($record->exists)
                            <a href="{{ route('head-office.inspection.show', $purchaseOrder) }}">Acceptance / Completion</a>
                            <a href="{{ route('head-office.inspection.print', $purchaseOrder) }}" target="_blank" rel="noopener">Print</a>
                        @endif
                    </section>
                    @if ($errors->any())
                        <div class="iar-editor-errors" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
                    @endif
                    <div class="iar-editor-canvas">@include('head-office.inspection.partials.report', ['editable' => true])</div>
                    @if (! $iarLocked)<div class="iar-editor-notes no-print">
                        <label for="delivery_receipt_number">Delivery Receipt No.<input id="delivery_receipt_number" name="delivery_receipt_number" maxlength="120" value="{{ old('delivery_receipt_number', $record->delivery_receipt_number) }}"></label>
                        <label for="findings">Inspection Findings<textarea id="findings" name="findings" rows="2">{{ old('findings', $record->findings) }}</textarea></label>
                        <label for="remarks">Remarks<textarea id="remarks" name="remarks" rows="2">{{ old('remarks', $record->remarks) }}</textarea></label>
                    </div>@endif
                </form>
            @else
                @php
                    $blankPurchaseOrder = new \App\Models\PurchaseOrder();
                    $blankPurchaseOrder->setRelation('items', collect());
                    $blankPurchaseOrder->setRelation('sourcePrDocument', null);
                @endphp
                <section class="po-workspace iar-template-preview" data-iar-blank-template>
                    <section class="po-toolbar no-print">
                        <div><strong>Inspection and Acceptance Report</strong><span>Official Municipality of Tomas Oppus template</span></div>
                        <a href="{{ route('head-office.inspection.index') }}">Change Source</a>
                        <a href="{{ route('document-templates.download', ['documentType' => 'inspection-acceptance']) }}">Download Editable Template</a>
                        <a href="{{ route('head-office.inspection.template-print') }}" target="_blank" rel="noopener">Print Template</a>
                    </section>
                    <div class="iar-source-hint no-print">Select a Purchase Order to fill in delivery details and save an inspection record.</div>
                    <div class="iar-editor-canvas">@include('head-office.inspection.partials.report', ['purchaseOrder' => $blankPurchaseOrder, 'record' => new \App\Models\InspectionAcceptanceRecord()])</div>
                </section>
            @endif
        </x-slot:form>
    </x-documents.document-create-flow>
@endsection
