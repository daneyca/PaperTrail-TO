@extends('layouts.dashboard')

@section('title', 'Create APP | PaperTrail')

@section('content')
    <form
        method="POST"
        action="{{ route('bac-secretariat.app.store') }}"
        class="app-editor-form document-create-flow"
        data-document-create-flow
        data-document-create-autoshow="{{ $errors->any() ? 'true' : 'false' }}"
    >
        @csrf
        <section class="document-create-source-card no-print" data-document-create-source aria-label="APP source and metadata">
            <div class="document-create-source-card__header">
                <div class="document-create-source-card__step" aria-hidden="true">1</div>
                <div>
                    <p class="eyebrow">APP Creation</p>
                    <h2>Create Annual Procurement Plan</h2>
                    <p>
                        @if ($selectedPpmpMode ?? false)
                            Review the selected PPMP reference before opening the landscape worksheet.
                        @else
                            Review the APP details and available PPMP references before opening the landscape worksheet.
                        @endif
                    </p>
                </div>
            </div>

            <div class="document-create-source-card__body">
                <section class="app-metadata-panel no-print" aria-label="APP metadata">
                    <div>
                        <label for="app-number-meta">APP No.</label>
                        <input id="app-number-meta" type="text" data-app-meta-field="app_number" value="{{ old('app_number', $app->app_number) }}" placeholder="Auto on submit">
                    </div>
                    <div>
                        <label for="app-year-meta">Fiscal Year</label>
                        <input id="app-year-meta" type="number" min="2020" max="2100" data-app-meta-field="fiscal_year" value="{{ old('fiscal_year', $app->fiscal_year ?? now()->year) }}">
                    </div>
                    <div>
                        <label for="app-title-meta">Title</label>
                        <input id="app-title-meta" type="text" data-app-meta-field="title" value="{{ old('title', $app->title ?? 'Annual Procurement Plan (APP) for CY ' . now()->year) }}">
                    </div>
                    <div>
                        <label for="app-office-meta">Office</label>
                        <input id="app-office-meta" type="text" data-app-meta-field="office_name" value="{{ old('office_name', $app->office_name ?? auth()->user()?->office) }}">
                    </div>
                    <div>
                        <label>APP Version</label>
                        <div class="app-version-readout">
                            <strong>Version {{ $appVersionContext['version_no'] ?? 1 }}</strong>
                            <span>
                                @if ($appVersionContext['is_update'] ?? false)
                                    @if (filled($appVersionContext['source_number'] ?? null))
                                        Carries forward {{ $appVersionContext['source_number'] }}; add newly accepted PPMPs below.
                                    @else
                                        Update version is assigned automatically when additional APP entries are added.
                                    @endif
                                @else
                                    First APP record for this fiscal year.
                                @endif
                            </span>
                        </div>
                    </div>
                </section>

                <section class="app-support-grid app-support-grid--single no-print" aria-label="APP support panels">
                    <div class="app-support-panel">
                        @include('bac-secretariat.app.partials.ppmp-consolidation-panel', [
                            'acceptedPpmps' => $acceptedPpmps ?? collect(),
                            'additionalPpmps' => $additionalPpmps ?? collect(),
                            'acceptedPpmpImportItems' => $acceptedPpmpImportItems ?? [],
                            'pendingPpmps' => $pendingPpmps ?? collect(),
                            'app' => $app,
                            'canImport' => false,
                            'createMode' => true,
                            'selectedPpmpMode' => $selectedPpmpMode ?? false,
                        ])
                    </div>
                </section>
            </div>

            <div class="document-create-source-card__actions">
                <a href="{{ route('bac-secretariat.app.index') }}" class="document-create-secondary-action">Back to APP</a>
                <button type="button" class="document-create-proceed-button" data-document-create-proceed>Proceed to APP Worksheet</button>
            </div>

            <p class="document-create-source-error" data-document-create-error hidden>Please review the APP source details before proceeding.</p>
        </section>

        <div class="document-create-form" data-document-create-form>
            <section class="app-toolbar app-worksheet-toolbar no-print">
                <div>
                    <strong>Create Annual Procurement Plan</strong>
                    <span>Edit directly inside the landscape APP form.</span>
                </div>
                <a href="{{ route('bac-secretariat.app.index') }}">Back</a>
                <button type="submit" name="save_action" value="draft">Save Draft</button>
                <button type="submit" name="save_action" value="submit" data-confirm="Submit this APP for approval?">Submit</button>
                <button type="button" onclick="addAppRow()">Add Row</button>
                <button type="button" onclick="removeEmptyAppRows()">Remove Empty Rows</button>
                <button type="button" onclick="printAppDocument()">Print</button>
            </section>

            @include('bac-secretariat.app.partials.app-landscape-form', [
                'app' => $app,
                'mode' => 'create',
            ])
        </div>
    </form>
@endsection
