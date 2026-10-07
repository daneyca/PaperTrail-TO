@extends('layouts.dashboard')

@section('title', 'Create BAC Resolution | PaperTrail')

@section('content')
    @php
        $selectedSourcePrId = old('source_pr_document_id', request('source_pr_document_id', $sourceDocument?->id));
    @endphp

    <x-documents.document-create-flow
        eyebrow="BAC Resolution Creation"
        title="Create BAC Resolution"
        description=""
        proceed-label="Proceed to Resolution"
        :back-url="route('bac-secretariat.resolutions.index')"
        back-label="Back to Resolutions"
        :autoshow="$errors->any()"
        :workflow-steps="['Select Procurement Record', 'Review Details', 'Prepare Resolution', 'Assign Signatories', 'Submit for Signature']"
        :form-step="3"
    >
        <x-slot:source>
            <x-documents.create-source-split
                title="Recent BAC Resolution Drafts"
                :drafts="$recentDrafts ?? collect()"
                document-type-label="BAC Resolution"
                source-title="Select Resolution Source"
            >
                <form method="GET" action="{{ route('bac-secretariat.resolutions.create') }}" class="resolution-source-picker no-print">
                    <label for="source_pr_document_id">
                        Source Purchase Request
                        <select id="source_pr_document_id" name="source_pr_document_id" onchange="this.form.submit()">
                            <option value="">Manual BAC Resolution</option>
                            @foreach ($eligiblePrs as $pr)
                                <option value="{{ $pr->id }}" @selected((string) $selectedSourcePrId === (string) $pr->id)>
                                    {{ $pr->pr_no ?? $pr->tracking_number }} - {{ $pr->title }} ({{ $pr->submittingOffice?->name ?? 'N/A' }})
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <noscript><button type="submit">Use Source</button></noscript>
                </form>
            </x-documents.create-source-split>
        </x-slot:source>

        <x-slot:form>
            <form method="POST" action="{{ route('bac-secretariat.resolutions.store') }}" class="resolution-workspace resolution-editor-form">
                @csrf
                <div class="resolution-page-toolbar no-print">
                    <div>
                        <strong>Create BAC Resolution</strong>
                        <span>Directly edit the official BAC Resolution document.</span>
                    </div>
                    <a href="{{ route('bac-secretariat.resolutions.index') }}">Back</a>
                    <button type="submit" name="save_action" value="draft">Save Draft</button>
                    <button type="submit" name="save_action" value="submit" onclick="return confirm('Save and submit this BAC Resolution for electronic signatures?');">Submit for Signatures</button>
                    <button type="button" class="secondary-button" onclick="printResolutionDocument()">Print Preview</button>
                </div>

                @include('bac-secretariat.resolutions.partials.resolution-word-editor', [
                    'resolution' => $resolution,
                    'sourceDocument' => $sourceDocument,
                    'mode' => 'create',
                ])
            </form>
        </x-slot:form>
    </x-documents.document-create-flow>
@endsection
