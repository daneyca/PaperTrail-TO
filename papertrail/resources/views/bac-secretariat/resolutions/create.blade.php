@extends('layouts.dashboard')

@section('title', 'Create BAC Resolution | PaperTrail')

@section('content')
    @php
        $selectedSourcePrId = old('source_pr_document_id', request('source_pr_document_id', $sourceDocument?->id));
        $isResolutionPreparer = auth()->user()?->user_id === 'BACSEC-001';
        $backUrl = $isResolutionPreparer ? route('bac-secretariat.pr.index') : route('bac-secretariat.resolutions.index');
    @endphp

    <x-documents.document-create-flow
        eyebrow="BAC Resolution Creation"
        title="Create BAC Resolution"
        description=""
        proceed-label="Proceed to Resolution"
        :back-url="$backUrl"
        :back-label="$isResolutionPreparer ? 'Back to PR Copies' : 'Back to Resolutions'"
        error-message="Select an assigned PR copy before proceeding."
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
                        <select id="source_pr_document_id" name="source_pr_document_id" onchange="this.form.submit()" @if ($isResolutionPreparer) data-document-create-required @endif>
                            <option value="">{{ $isResolutionPreparer ? 'Select assigned PR copy' : 'Manual BAC Resolution' }}</option>
                            @foreach ($eligiblePrs as $pr)
                                <option value="{{ $pr->id }}" @selected((string) $selectedSourcePrId === (string) $pr->id)>
                                    {{ $pr->pr_no ?? $pr->tracking_number }} - {{ $pr->title }} ({{ $pr->submittingOffice?->name ?? 'N/A' }})
                                </option>
                            @endforeach
                        </select>
                    </label>
                    @if ($isResolutionPreparer && $eligiblePrs->isEmpty())
                        <p>No PR copy is assigned to you yet. It will appear here when the requesting office forwards one.</p>
                    @endif
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
                    <a href="{{ $backUrl }}">Back</a>
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
