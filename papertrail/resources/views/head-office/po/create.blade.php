@extends('layouts.dashboard')

@section('title', 'Create Purchase Order | PaperTrail')

@section('content')
    @php
        $poDrafts = collect($recentDrafts ?? [])->values();
        $visiblePoDrafts = $poDrafts->take(5);
    @endphp

    <x-documents.document-create-flow
        eyebrow="Purchase Order Creation"
        title="Create Purchase Order"
        description=""
        proceed-label="Proceed to PO Form"
        :back-url="route('head-office.purchase-orders.index')"
        back-label="Back to Purchase Orders"
        :autoshow="$errors->any()"
        :workflow-steps="['Select Source', 'Review Supplier Details', 'Prepare Purchase Order', 'Submit']"
        :form-step="3"
    >
        <x-slot:source>
            <div class="po-create-split-layout">
                <aside class="po-create-drafts-pane" aria-labelledby="recent-po-drafts-title">
                    <header class="po-create-pane-heading">
                        <div>
                            <p class="eyebrow">Recent Drafts</p>
                            <h3 id="recent-po-drafts-title">Recent PO Drafts</h3>
                        </div>
                        <span>{{ $poDrafts->count() }}</span>
                    </header>

                    <div class="po-create-draft-list">
                        @forelse ($visiblePoDrafts as $draft)
                            @php
                                $draftNumber = $draft['number'] ?? 'Draft';
                                $draftStatus = $draft['status_label'] ?? str($draft['status'] ?? 'draft')->replace(['_', '-'], ' ')->title();
                                $draftEditUrl = $draft['edit_url'] ?? null;
                                $draftShowUrl = $draft['show_url'] ?? null;
                            @endphp

                            <article class="po-create-draft-card po-create-draft-card--compact">
                                <strong class="po-create-draft-number">{{ $draftNumber }}</strong>
                                <span class="document-create-draft-status">{{ $draftStatus }}</span>

                                <div class="document-create-draft-icon-actions">
                                    @if ($draftEditUrl)
                                        <a href="{{ $draftEditUrl }}" class="po-create-draft-icon-action" aria-label="Edit draft" title="Edit draft">
                                            <x-papertrail.icon name="edit" />
                                        </a>
                                    @endif

                                    @if ($draftShowUrl)
                                        <a href="{{ $draftShowUrl }}" class="po-create-draft-icon-action" aria-label="View draft" title="View draft">
                                            <x-papertrail.icon name="view" />
                                        </a>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="po-create-drafts-empty">
                                <strong>No draft Purchase Orders yet.</strong>
                            </div>
                        @endforelse
                    </div>
                </aside>

                <section class="po-create-source-pane" aria-labelledby="po-source-picker-title">
                    <header class="po-create-pane-heading">
                        <div>
                            <p class="eyebrow">Source</p>
                            <h3 id="po-source-picker-title">Select PO Source</h3>
                        </div>
                    </header>

                    <form method="GET" action="{{ route('head-office.purchase-orders.create') }}" class="po-source-picker po-source-picker--split no-print">
                        <label for="source_abstract_id">
                            Source Abstract
                            <select id="source_abstract_id" name="source_abstract_id" onchange="this.form.submit()">
                                <option value="">Manual Purchase Order</option>
                                @foreach ($eligibleAbstracts as $abstract)
                                    <option value="{{ $abstract->id }}" @selected((string) request('source_abstract_id') === (string) $abstract->id)>
                                        {{ $abstract->abstract_number ?? 'Draft Abstract' }} - {{ $abstract->project_name ?? $abstract->sourcePrDocument?->title ?? 'Untitled' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label for="source_pr_document_id">
                            Source Purchase Request
                            <select id="source_pr_document_id" name="source_pr_document_id" onchange="this.form.submit()">
                                <option value="">Use source PR directly</option>
                                @foreach ($eligiblePrs as $pr)
                                    <option value="{{ $pr->id }}" @selected((string) request('source_pr_document_id') === (string) $pr->id)>
                                        {{ $pr->tracking_number }} - {{ $pr->title }} ({{ $pr->submittingOffice?->name ?? 'N/A' }})
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <noscript><button type="submit">Use Source</button></noscript>
                    </form>
                </section>
            </div>
        </x-slot:source>

        <x-slot:form>
            <form method="POST" action="{{ route('head-office.purchase-orders.store') }}" class="po-workspace po-editor-form">
                @csrf
                <section class="po-toolbar no-print">
                    <div>
                        <strong>Create Purchase Order</strong>
                        <span>Edit directly inside the official LGU Purchase Order form.</span>
                    </div>
                    <a href="{{ route('head-office.purchase-orders.index') }}">Back</a>
                    <button type="submit" name="save_action" value="draft">Save Draft</button>
                    <button type="submit" name="save_action" value="submit" onclick="return confirm('Submit this Purchase Order?');">Submit</button>
                    <button type="button" onclick="addPurchaseOrderRow()">Add Row</button>
                    <button type="button" id="removeEmptyPoRowsBtn">Remove Empty Rows</button>
                    <button type="button" onclick="printPurchaseOrderDocument()">Print</button>
                </section>

                @include('head-office.purchase-orders.partials.purchase-order-excel-form', [
                    'po' => $po,
                    'sourceDocument' => $sourceDocument,
                    'sourceAbstract' => $sourceAbstract,
                    'mode' => 'create',
                ])
            </form>
        </x-slot:form>
    </x-documents.document-create-flow>
@endsection
