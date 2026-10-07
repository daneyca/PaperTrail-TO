@extends('layouts.dashboard')

@section('title', 'Create Purchase Order | PaperTrail')

@section('content')
    <x-documents.document-create-flow
        eyebrow="Purchase Order Creation"
        title="Create Purchase Order"
        description=""
        proceed-label="Proceed to PO Form"
        :back-url="route('bac-secretariat.purchase-orders.index')"
        back-label="Back to Purchase Orders"
        :autoshow="$errors->any()"
        :workflow-steps="['Select Source', 'Review Supplier Details', 'Prepare Purchase Order', 'Submit']"
        :form-step="3"
    >
        <x-slot:source>
            <x-documents.create-source-split
                title="Recent PO Drafts"
                :drafts="$recentDrafts ?? collect()"
                document-type-label="Purchase Order"
                source-title="Select PO Source"
            >
                <form method="GET" action="{{ route('bac-secretariat.purchase-orders.create') }}" class="po-source-picker no-print">
                    <label for="source_pr_document_id">
                        Source Purchase Request
                        <select id="source_pr_document_id" name="source_pr_document_id" onchange="this.form.submit()">
                            <option value="">Manual Purchase Order</option>
                            @foreach ($eligiblePrs as $pr)
                                <option value="{{ $pr->id }}" @selected((string) request('source_pr_document_id') === (string) $pr->id)>
                                    {{ $pr->tracking_number }} - {{ $pr->title }} ({{ $pr->submittingOffice?->name ?? 'N/A' }})
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <noscript><button type="submit">Use Source</button></noscript>
                </form>
            </x-documents.create-source-split>
        </x-slot:source>

        <x-slot:form>
            <form method="POST" action="{{ route('bac-secretariat.purchase-orders.store') }}" class="po-workspace po-editor-form">
                @csrf
                <section class="po-toolbar no-print">
                    <div>
                        <strong>Create Purchase Order</strong>
                        <span>Edit directly inside the official LGU Purchase Order form.</span>
                    </div>
                    <a href="{{ route('bac-secretariat.purchase-orders.index') }}">Back</a>
                    <button type="submit" name="save_action" value="draft">Save Draft</button>
                    <button type="submit" name="save_action" value="submit" onclick="return confirm('Submit this Purchase Order?');">Submit</button>
                    <button type="button" onclick="addPurchaseOrderRow()">Add Row</button>
                    <button type="button" id="removeEmptyPoRowsBtn">Remove Empty Rows</button>
                    <button type="button" onclick="printPurchaseOrderDocument()">Print</button>
                </section>

                @include('bac-secretariat.purchase-orders.partials.purchase-order-excel-form', [
                    'po' => $po,
                    'sourceDocument' => $sourceDocument,
                    'mode' => 'create',
                ])
            </form>
        </x-slot:form>
    </x-documents.document-create-flow>
@endsection
