@extends('layouts.dashboard')

@section('title', 'Create Abstract | PaperTrail')

@section('content')
    <x-documents.document-create-flow
        eyebrow="Abstract Creation"
        title="Create Abstract"
        description=""
        proceed-label="Proceed to Abstract Form"
        :back-url="route('bac-secretariat.abstracts.index')"
        back-label="Back to Abstracts"
        :autoshow="$errors->any()"
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
                <form method="GET" action="{{ route('bac-secretariat.abstracts.create') }}" class="abstract-source-picker no-print">
                    <label for="source_rfq_id">
                        Source RFQ
                        <select id="source_rfq_id" name="source_rfq_id" onchange="this.form.submit()">
                            <option value="">Manual Abstract</option>
                            @foreach ($eligibleRfqs as $rfq)
                                <option value="{{ $rfq->id }}" @selected((string) request('source_rfq_id') === (string) $rfq->id)>
                                    {{ $rfq->rfq_number ?? 'Draft RFQ' }} - {{ $rfq->purpose ?? $rfq->sourcePrDocument?->title ?? 'Untitled' }}
                                </option>
                            @endforeach
                            @if ($eligibleRfqs->isEmpty())
                                <option value="" disabled>No RFQs with BAC Resolution ready for Abstract</option>
                            @endif
                        </select>
                    </label>
                    <noscript><button type="submit">Use Source</button></noscript>
                </form>
            </x-documents.create-source-split>
        </x-slot:source>

        <x-slot:form>
            <form method="POST" action="{{ route('bac-secretariat.abstracts.store') }}" class="abstract-editor-form">
                @csrf
                <section class="abstract-toolbar no-print">
                    <div><strong>Create Abstract</strong><span>Edit directly inside the official Abstract form.</span></div>
                    <a href="{{ route('bac-secretariat.abstracts.index') }}">Back</a>
                    <button type="submit" name="save_action" value="draft">Save Draft</button>
                    <button type="submit" name="save_action" value="submit" onclick="return confirm('Submit this Abstract?');">Submit</button>
                    <button type="button" onclick="addAbstractRow()">Add Row</button>
                    <button type="button" onclick="removeEmptyAbstractRows()">Remove Empty Rows</button>
                    <button type="button" onclick="printAbstractDocument()">Print</button>
                </section>

                @include('bac-secretariat.abstracts.partials.abstract-excel-form', [
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
