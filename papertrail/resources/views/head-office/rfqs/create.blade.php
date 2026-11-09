@extends('layouts.dashboard')

@section('title', 'Create RFQ | PaperTrail')

@section('content')
    <x-documents.document-create-flow
        eyebrow="RFQ Creation"
        title="Create RFQ"
        description=""
        proceed-label="Proceed to RFQ Form"
        :back-url="route('head-office.rfqs.index')"
        back-label="Back to RFQs"
        :autoshow="$errors->any()"
        :workflow-steps="['Select PR Source', 'Review Items', 'Create RFQ Form', 'Submit RFQ']"
        :form-step="3"
    >
        <x-slot:source>
            @php
                $selectedPrId = request('source_pr_document_id');
                $selectedResolutionId = request('source_bac_resolution_id');
            @endphp
            <x-documents.create-source-split
                title="Recent RFQ Drafts"
                :drafts="$recentDrafts ?? collect()"
                document-type-label="RFQ"
                source-title="Select RFQ Source"
            >
                <x-slot:existingRecords>
                    <section aria-label="Existing RFQs" style="margin-top: 18px;">
                        <header class="document-create-split-heading">
                            <div><p class="eyebrow">Submitted / Processed</p><h3>Existing RFQs</h3></div>
                            <span>{{ ($existingRfqs ?? collect())->count() }}</span>
                        </header>
                        <div class="document-create-draft-list">
                            @forelse ($existingRfqs ?? collect() as $existingRfq)
                                @php
                                    $existingPr = $existingRfq->sourcePrDocument ?? $existingRfq->sourceBacResolution?->sourcePrDocument;
                                @endphp
                                <article class="document-create-draft-card" data-existing-rfq="{{ $existingRfq->id }}">
                                    <strong class="document-create-draft-number">{{ $existingRfq->rfq_number ?? $existingRfq->document_reference_number }}</strong>
                                    <span class="document-create-draft-status">{{ $existingRfq->control_assigned_at && $existingRfq->status === \App\Models\Rfq::STATUS_SUBMITTED ? 'Number and Date Assigned' : str($existingRfq->status)->replace('_', ' ')->title() }}</span>
                                    @if ($existingRfq->rfq_number && $existingRfq->document_reference_number)
                                        <small>Tracking: {{ $existingRfq->document_reference_number }}</small>
                                    @endif
                                    @if ($existingPr)
                                        <small>PR: {{ $existingPr->pr_no ?? $existingPr->tracking_number }}</small>
                                    @endif
                                    <a href="{{ route('head-office.rfqs.show', $existingRfq) }}" class="document-create-secondary-action">Open RFQ</a>
                                </article>
                            @empty
                                <div class="document-create-drafts-empty"><strong>No submitted RFQs yet.</strong></div>
                            @endforelse
                        </div>
                        <a href="{{ route('head-office.rfqs.index') }}" class="document-create-secondary-action" style="margin-top: 12px;">View All RFQs</a>
                    </section>
                </x-slot:existingRecords>
                <form method="GET" action="{{ route('head-office.rfqs.create') }}" class="rfq-source-picker no-print">
                    <label for="source_pr_document_id">
                        Ready PR Source
                        <select
                            id="source_pr_document_id"
                            name="source_pr_document_id"
                            onchange="document.getElementById('source_bac_resolution_id').value = ''; this.form.submit()"
                        >
                            <option value="">Manual RFQ</option>
                            @foreach ($eligiblePrs as $pr)
                                @php
                                    $prNumber = $pr->pr_no ?? $pr->tracking_number ?? 'No PR number';
                                    $officeName = $pr->submittingOffice?->name ?? $pr->department_name ?? 'Requesting Office';
                                @endphp
                                <option value="{{ $pr->id }}" @selected(blank($selectedResolutionId) && (string) $selectedPrId === (string) $pr->id)>
                                    {{ $prNumber }} - {{ $officeName }} - {{ $pr->title ?? $pr->purpose ?? 'Untitled PR' }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label for="source_bac_resolution_id">
                        BAC Resolution Source
                        <select
                            id="source_bac_resolution_id"
                            name="source_bac_resolution_id"
                            onchange="document.getElementById('source_pr_document_id').value = ''; this.form.submit()"
                        >
                            <option value="">No BAC Resolution selected</option>
                            @foreach ($eligibleResolutions as $resolution)
                                @php
                                    $sourcePr = $resolution->sourcePrDocument;
                                    $sourceNumber = $sourcePr?->pr_no ?? $sourcePr?->tracking_number ?? 'No PR number';
                                    $sourceStatus = $sourcePr?->status
                                        ? str($sourcePr->status)->replace('_', ' ')->title()
                                        : 'Ready for RFQ';
                                @endphp
                                <option value="{{ $resolution->id }}" @selected((string) $selectedResolutionId === (string) $resolution->id)>
                                    {{ $resolution->resolution_number ?? 'Draft Resolution' }} - {{ $sourceNumber }} - {{ $resolution->title ?? $resolution->project_title ?? 'Untitled' }} ({{ $sourceStatus }})
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <noscript><button type="submit">Use Source</button></noscript>
                </form>
                @if (($existingRfqs ?? collect())->isNotEmpty())
                    <p>PRs already used for an RFQ appear under <strong>Existing RFQs</strong>. Select <strong>Open RFQ</strong> to view the assigned number and date.</p>
                @endif
            </x-documents.create-source-split>
        </x-slot:source>

        <x-slot:form>
            <form method="POST" action="{{ route('head-office.rfqs.store') }}" class="rfq-editor-form">
                @csrf
                <section class="rfq-toolbar no-print">
                    <div><strong>Create RFQ</strong><span>Edit directly inside the official RFQ form.</span></div>
                    <a href="{{ route('head-office.rfqs.index') }}">Back</a>
                    <button type="submit" name="save_action" value="draft">Save Draft</button>
                    <button type="submit" name="save_action" value="submit" onclick="return confirm('Submit this RFQ?');">Submit</button>
                    <button type="button" onclick="addRfqRow()">Add Row</button>
                    <button type="button" id="removeEmptyRfqRowsBtn">Remove Empty Rows</button>
                    <button type="button" onclick="printRfqDocument()">Print</button>
                </section>

                @include('head-office.rfqs.partials.rfq-excel-form', [
                    'rfq' => $rfq,
                    'sourceDocument' => $sourceDocument,
                    'sourceResolution' => $sourceResolution,
                    'mode' => 'create',
                ])
            </form>
        </x-slot:form>
    </x-documents.document-create-flow>
@endsection
