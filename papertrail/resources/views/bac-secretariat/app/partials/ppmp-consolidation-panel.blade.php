@php
    $importedPpmps = collect($importedPpmps ?? []);
    $acceptedPpmps = collect($acceptedPpmps ?? []);
    $additionalPpmps = collect($additionalPpmps ?? []);
    $pendingPpmps = collect($pendingPpmps ?? []);
    $acceptedPpmpImportItems = collect($acceptedPpmpImportItems ?? []);
    $lockedPpmpIds = collect($lockedPpmpIds ?? [])->map(fn ($id) => (int) $id);
    $canImport = (bool) ($canImport ?? false);
    $createMode = (bool) ($createMode ?? false);
    $selectedPpmpMode = (bool) ($selectedPpmpMode ?? false);
    $app = $app ?? null;

    $ppmpStatusLabel = function (?string $status): string {
        return match ($status) {
            \App\Models\ProcurementDocument::STATUS_PENDING_PPMP_REVIEW => 'Submitted to BAC',
            \App\Models\ProcurementDocument::STATUS_UNDER_PPMP_REVIEW => 'Under APP Consolidation',
            \App\Models\ProcurementDocument::STATUS_ACCEPTED_FOR_APP_CONSOLIDATION => 'Approved',
            default => str($status ?: 'record')->replace('_', ' ')->title()->toString(),
        };
    };
@endphp

<strong>{{ $selectedPpmpMode ? 'Selected PPMP for APP Consolidation' : 'PPMP Consolidation' }}</strong>

@if ($selectedPpmpMode)
    <p>Only the selected PPMP is loaded. Additional approved PPMPs may be added manually if needed.</p>
@endif

@if ($pendingPpmps->isNotEmpty())
    <p>{{ $pendingPpmps->count() }} incoming PPMP record{{ $pendingPpmps->count() === 1 ? '' : 's' }} need review before APP import.</p>
    <div class="app-ppmp-import-list app-ppmp-review-list">
        @foreach ($pendingPpmps as $ppmp)
            <div class="app-ppmp-import-row">
                <span>
                    <strong>{{ $ppmp->tracking_number ?? $ppmp->ppmp_no ?? 'PPMP #' . $ppmp->id }}</strong>
                    {{ $ppmp->submittingOffice?->name ?? 'Requesting Office' }} - {{ $ppmpStatusLabel($ppmp->status) }} - PHP {{ number_format((float) $ppmp->total_amount, 2) }}
                </span>
                <a href="{{ route('bac-secretariat.ppmp.show', $ppmp) }}">Review / Accept</a>
            </div>
        @endforeach
    </div>
@endif

@if ($canImport && ! $createMode && $importedPpmps->isNotEmpty())
    <p>{{ $importedPpmps->count() }} PPMP record{{ $importedPpmps->count() === 1 ? '' : 's' }} already added to this APP draft.</p>
    <div class="app-ppmp-import-list">
        @foreach ($importedPpmps as $ppmp)
            @php
                $isLockedPpmp = $lockedPpmpIds->contains((int) $ppmp->id);
            @endphp
            @if ($isLockedPpmp)
                <div class="app-ppmp-import-row">
                    <span>
                        <strong>{{ $ppmp->tracking_number ?? $ppmp->ppmp_no ?? 'PPMP #' . $ppmp->id }}</strong>
                        {{ $ppmp->submittingOffice?->name ?? 'Requesting Office' }} - PHP {{ number_format((float) $ppmp->total_amount, 2) }}
                    </span>
                    <span class="status-pill status-submitted">Locked after submission</span>
                </div>
            @else
                <form
                    method="POST"
                    action="{{ route('bac-secretariat.app.remove-ppmp', [$app, $ppmp]) }}"
                    class="app-ppmp-import-row"
                    data-confirm-title="Remove PPMP from APP?"
                    data-confirm="This removes the imported rows from this APP draft only. The source PPMP remains accepted and can be added again. Unsaved worksheet edits on this page will not be saved."
                    data-confirm-label="Remove from APP"
                    data-confirm-type="danger"
                >
                    @csrf
                    @method('DELETE')
                    <span>
                        <strong>{{ $ppmp->tracking_number ?? $ppmp->ppmp_no ?? 'PPMP #' . $ppmp->id }}</strong>
                        {{ $ppmp->submittingOffice?->name ?? 'Requesting Office' }} - PHP {{ number_format((float) $ppmp->total_amount, 2) }}
                    </span>
                    <button type="submit" class="is-remove">Remove from APP</button>
                </form>
            @endif
        @endforeach
    </div>
@endif

@if ($canImport)
    @if ($acceptedPpmps->isNotEmpty())
        <p>Add newly approved PPMP records into this {{ $app?->plan_type === 'update' ? 'APP update' : 'APP' }} worksheet.</p>
        <div class="app-ppmp-import-list">
            @foreach ($acceptedPpmps as $ppmp)
                <form method="POST" action="{{ route('bac-secretariat.app.import-ppmp', $app) }}" class="app-ppmp-import-row">
                    @csrf
                    <input type="hidden" name="ppmp_document_id" value="{{ $ppmp->id }}">
                    <span>
                        <strong>{{ $ppmp->tracking_number ?? $ppmp->ppmp_no ?? 'PPMP #' . $ppmp->id }}</strong>
                        {{ $ppmp->submittingOffice?->name ?? 'Requesting Office' }} - PHP {{ number_format((float) $ppmp->total_amount, 2) }}
                    </span>
                    <button type="submit">Add</button>
                </form>
            @endforeach
        </div>
    @elseif ($importedPpmps->isNotEmpty() && $pendingPpmps->isEmpty())
        <p>Only PPMP records already added to this APP draft are shown here. Add other ready PPMPs from the APP Consolidation list.</p>
    @elseif ($pendingPpmps->isEmpty())
        <p>No PPMP records have been added to this APP draft yet. Add ready PPMPs from the APP Consolidation list.</p>
    @else
        <p>Accept the incoming PPMP records above so they become available for APP import.</p>
    @endif
@elseif ($createMode && $acceptedPpmps->isNotEmpty())
    @if ($selectedPpmpMode)
        @foreach ($acceptedPpmps as $ppmp)
            @php
                $importItems = $acceptedPpmpImportItems->get($ppmp->id, []);
                $lineItemCount = count($importItems);
            @endphp
            <div class="app-selected-ppmp" data-app-create-ppmp-row="{{ $ppmp->id }}">
                <div class="app-selected-ppmp__header">
                    <span>
                        <small>Selected PPMP</small>
                        <strong>{{ $ppmp->tracking_number ?? $ppmp->ppmp_no ?? 'PPMP #' . $ppmp->id }}</strong>
                    </span>
                    <button
                        type="button"
                        data-app-add-ppmp
                        data-ppmp-id="{{ $ppmp->id }}"
                        data-ppmp-label="{{ $ppmp->tracking_number ?? $ppmp->ppmp_no ?? 'PPMP #' . $ppmp->id }}"
                        data-add-label="{{ empty($importItems) ? 'No Items' : 'Add to APP Worksheet' }}"
                        @disabled(empty($importItems))
                    >
                        {{ empty($importItems) ? 'No Items' : 'Add to APP Worksheet' }}
                    </button>
                </div>
                <dl class="app-selected-ppmp__details">
                    <div><dt>Requesting Office</dt><dd>{{ $ppmp->submittingOffice?->name ?? 'Requesting Office' }}</dd></div>
                    <div><dt>Fiscal Year</dt><dd>{{ $ppmp->fiscal_year ?? $app?->fiscal_year ?? 'N/A' }}</dd></div>
                    <div><dt>Approved Budget</dt><dd>PHP {{ number_format((float) $ppmp->total_amount, 2) }}</dd></div>
                    <div><dt>APP Rows</dt><dd>{{ $lineItemCount }} row{{ $lineItemCount === 1 ? '' : 's' }}</dd></div>
                </dl>
                <script type="application/json" data-app-ppmp-items="{{ $ppmp->id }}">@json($importItems)</script>
            </div>
        @endforeach

        <p class="app-import-feedback" data-app-import-feedback hidden></p>

        @if ($additionalPpmps->isNotEmpty())
            <details class="app-add-more-ppmps">
                <summary>Add More PPMPs</summary>
                <p>Choose additional approved PPMPs only when they should be consolidated into this same APP worksheet.</p>
                <div class="app-ppmp-import-list">
                    @foreach ($additionalPpmps as $ppmp)
                        @php
                            $importItems = $acceptedPpmpImportItems->get($ppmp->id, []);
                        @endphp
                        <div class="app-ppmp-import-row" data-app-create-ppmp-row="{{ $ppmp->id }}">
                            <span>
                                <strong>{{ $ppmp->tracking_number ?? $ppmp->ppmp_no ?? 'PPMP #' . $ppmp->id }}</strong>
                                {{ $ppmp->submittingOffice?->name ?? 'Requesting Office' }} - PHP {{ number_format((float) $ppmp->total_amount, 2) }}
                            </span>
                            <button
                                type="button"
                                data-app-add-ppmp
                                data-ppmp-id="{{ $ppmp->id }}"
                                data-ppmp-label="{{ $ppmp->tracking_number ?? $ppmp->ppmp_no ?? 'PPMP #' . $ppmp->id }}"
                                data-add-label="{{ empty($importItems) ? 'No Items' : 'Add to APP' }}"
                                @disabled(empty($importItems))
                            >
                                {{ empty($importItems) ? 'No Items' : 'Add to APP' }}
                            </button>
                            <script type="application/json" data-app-ppmp-items="{{ $ppmp->id }}">@json($importItems)</script>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    @else
        <p>Add approved PPMP records directly into this {{ $app?->plan_type === 'update' ? 'APP update' : 'APP' }} worksheet, then save or submit the APP.</p>
        <div class="app-ppmp-import-list">
            @foreach ($acceptedPpmps as $ppmp)
                @php
                    $importItems = $acceptedPpmpImportItems->get($ppmp->id, []);
                @endphp
                <div class="app-ppmp-import-row" data-app-create-ppmp-row="{{ $ppmp->id }}">
                    <span>
                        <strong>{{ $ppmp->tracking_number ?? $ppmp->ppmp_no ?? 'PPMP #' . $ppmp->id }}</strong>
                        {{ $ppmp->submittingOffice?->name ?? 'Requesting Office' }} - PHP {{ number_format((float) $ppmp->total_amount, 2) }}
                    </span>
                    <button
                        type="button"
                        data-app-add-ppmp
                        data-ppmp-id="{{ $ppmp->id }}"
                        data-ppmp-label="{{ $ppmp->tracking_number ?? $ppmp->ppmp_no ?? 'PPMP #' . $ppmp->id }}"
                        data-add-label="{{ empty($importItems) ? 'No Items' : 'Add to APP' }}"
                        @disabled(empty($importItems))
                    >
                        {{ empty($importItems) ? 'No Items' : 'Add to APP' }}
                    </button>
                    <script type="application/json" data-app-ppmp-items="{{ $ppmp->id }}">@json($importItems)</script>
                </div>
            @endforeach
        </div>
    @endif
@elseif ($acceptedPpmps->isNotEmpty())
        <p>{{ $acceptedPpmps->count() }} approved PPMP record{{ $acceptedPpmps->count() === 1 ? '' : 's' }} for CY {{ $app?->fiscal_year ?? now()->year }} can be added {{ $createMode ? 'after saving this APP as a draft' : 'from an editable APP draft' }}.</p>
@elseif ($pendingPpmps->isEmpty())
    <p>Approved PPMP records will be available for import after BACSEC-004 review.</p>
@else
    <p>Open the incoming PPMP record above, then accept it for APP consolidation.</p>
@endif
