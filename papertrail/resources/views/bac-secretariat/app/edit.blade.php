@extends('layouts.dashboard')

@section('title', 'Edit APP | PaperTrail')

@section('content')
    @php
        $canFinalizeWithExistingSignatures = (bool) ($appSignatureWorkflowComplete ?? false);
    @endphp

    <section class="app-support-grid app-support-grid--single no-print" aria-label="APP support panels">
        <div class="app-support-panel">
            @include('bac-secretariat.app.partials.ppmp-consolidation-panel', [
                'importedPpmps' => $importedPpmps ?? collect(),
                'acceptedPpmps' => $acceptedPpmps ?? collect(),
                'pendingPpmps' => $pendingPpmps ?? collect(),
                'lockedPpmpIds' => $lockedPpmpIds ?? collect(),
                'app' => $app,
                'canImport' => true,
                'selectedPpmpMode' => $selectedPpmpMode ?? false,
            ])
        </div>
    </section>

    <form method="POST" action="{{ route('bac-secretariat.app.update', $app) }}" class="app-editor-form">
        @csrf
        @method('PATCH')

        <section class="app-toolbar app-worksheet-toolbar no-print">
            <div>
                <strong>Edit {{ $app->app_number ?? 'APP Draft' }}</strong>
                <span>Edit directly inside the landscape APP form.</span>
            </div>
            <a href="{{ route('bac-secretariat.app.show', $app) }}">Back</a>
            <button type="submit" name="save_action" value="draft">Save Draft</button>
            <button
                type="submit"
                name="save_action"
                value="submit"
                data-confirm="{{ $canFinalizeWithExistingSignatures ? 'Finalize this APP update using the existing completed signatures?' : 'Submit this APP for approval?' }}"
            >
                {{ $canFinalizeWithExistingSignatures ? 'Finalize Update' : 'Submit' }}
            </button>
            <button type="button" onclick="addAppRow()">Add Row</button>
            <button type="button" onclick="removeEmptyAppRows()">Remove Empty Rows</button>
            <button type="button" onclick="printAppDocument()">Print</button>
        </section>

        @include('bac-secretariat.app.partials.app-landscape-form', [
            'app' => $app,
            'mode' => 'edit',
            'lockedPpmpIds' => $lockedPpmpIds ?? collect(),
            'signatoriesLocked' => $canFinalizeWithExistingSignatures,
        ])
    </form>
@endsection
