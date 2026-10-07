@extends('layouts.dashboard')

@section('title', 'Create RFQ | PaperTrail')

@section('content')
    <x-documents.document-create-flow
        eyebrow="RFQ Creation"
        title="Create RFQ"
        description=""
        proceed-label="Proceed to RFQ Form"
        :back-url="route('bac-secretariat.rfqs.index')"
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
                <form method="GET" action="{{ route('bac-secretariat.rfqs.create') }}" class="rfq-source-picker no-print">
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
                                <option value="{{ $resolution->id }}" @selected((string) $selectedResolutionId === (string) $resolution->id)>
                                    {{ $resolution->resolution_number ?? 'Draft Resolution' }} - {{ $resolution->title ?? $resolution->project_title ?? 'Untitled' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <noscript><button type="submit">Use Source</button></noscript>
                </form>
            </x-documents.create-source-split>
        </x-slot:source>

        <x-slot:form>
            <form method="POST" action="{{ route('bac-secretariat.rfqs.store') }}" class="rfq-editor-form">
                @csrf
                <section class="rfq-toolbar no-print">
                    <div><strong>Create RFQ</strong><span>Edit directly inside the official RFQ form.</span></div>
                    <a href="{{ route('bac-secretariat.rfqs.index') }}">Back</a>
                    <button type="submit" name="save_action" value="draft">Save Draft</button>
                    <button type="submit" name="save_action" value="submit" onclick="return confirm('Submit this RFQ?');">Submit</button>
                    <button type="button" onclick="addRfqRow()">Add Row</button>
                    <button type="button" onclick="removeEmptyRfqRows()">Remove Empty Rows</button>
                    <button type="button" onclick="printRfqDocument()">Print</button>
                </section>

                @include('bac-secretariat.rfqs.partials.rfq-excel-form', [
                    'rfq' => $rfq,
                    'sourceDocument' => $sourceDocument,
                    'sourceResolution' => $sourceResolution,
                    'mode' => 'create',
                ])
            </form>
        </x-slot:form>
    </x-documents.document-create-flow>
@endsection
