@extends('layouts.dashboard')

@section('title', 'Create Abstract | PaperTrail')

@section('content')
    @include('head-office.abstracts.partials.form-feedback')
    <x-documents.document-create-flow
        eyebrow="Abstract Creation"
        title="Create Abstract"
        description=""
        proceed-label="Proceed to Abstract Form"
        :back-url="route('head-office.abstracts.index')"
        back-label="Back to Abstracts"
        :autoshow="$errors->any() || session()->hasOldInput()"
        :workflow-steps="['Select RFQ', 'Review Quotations', 'Prepare Abstract', 'Submit']"
        :form-step="3"
    >
        <x-slot:source>
            <x-documents.create-source-split
                title="Recent Abstract Drafts"
                :drafts="$recentDrafts ?? collect()"
                document-type-label="Abstract"
                source-title="Select Abstract Source"
            >
                <form method="GET" action="{{ route('head-office.abstracts.create') }}" class="abstract-source-picker no-print">
                    <label for="source_rfq_id">
                        Ready RFQ Source
                        <select id="source_rfq_id" name="source_rfq_id" onchange="this.form.submit()">
                            <option value="">Blank Abstract Template</option>
                            @foreach ($eligibleRfqs as $rfq)
                                <option value="{{ $rfq->id }}" @selected((string) request('source_rfq_id') === (string) $rfq->id)>
                                    {{ $rfq->rfq_number ?? 'Draft RFQ' }} - {{ $rfq->purpose ?? $rfq->sourcePrDocument?->title ?? 'Untitled' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <noscript><button type="submit">Use Source</button></noscript>
                </form>
                @if (! $sourceRfq)
                    <p class="abstract-source-hint">Open the blank template to review, edit, or print it. Select a completed RFQ to save or submit an Abstract.</p>
                @endif
            </x-documents.create-source-split>
        </x-slot:source>

        <x-slot:form>
            <form method="POST" action="{{ route('head-office.abstracts.store') }}" class="abstract-editor-form">
                @csrf
                <section class="abstract-toolbar no-print">
                    <div><strong>Create Abstract</strong><span>Edit directly inside the official Abstract form.</span></div>
                    <a href="{{ route('head-office.abstracts.index') }}">Back</a>
                    @if ($sourceRfq)
                        <button type="submit" name="save_action" value="draft">Save Draft</button>
                        <button type="submit" name="save_action" value="submit" onclick="return confirm('Submit this Abstract?');">Submit</button>
                    @else
                        <a href="{{ route('head-office.abstracts.create') }}">Select RFQ to Save</a>
                        <a href="{{ route('document-templates.download', ['documentType' => 'abstract']) }}">Download Editable Template</a>
                    @endif
                    <button type="button" onclick="addAbstractRow()">Add Row</button>
                    <button type="button" id="removeEmptyAbstractRowsBtn">Remove Empty Rows</button>
                    <button type="button" onclick="printAbstractDocument()">Print</button>
                </section>

                @include('head-office.abstracts.partials.abstract-excel-form', [
                    'abstract' => $abstract,
                    'sourceDocument' => $sourceDocument,
                    'sourceRfq' => $sourceRfq,
                    'sourceResolution' => $sourceResolution,
                    'mode' => 'create',
                ])
            </form>
        </x-slot:form>
    </x-documents.document-create-flow>
@endsection
