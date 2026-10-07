@php
    $useDocumentCreateFlow = (bool) ($useDocumentCreateFlow ?? false);
    $ppmpOfficeName = $office?->name ?? $user->assignedOffice?->name ?? $user->office ?? 'your office';
    $ppmpRoleName = trim((string) ($user->assignedRole?->name ?? $user->role ?? 'Head of Office / End User'));
@endphp

@csrf
@if ($document->exists)
    @method('PATCH')
@endif

@if ($useDocumentCreateFlow)
    <section class="document-create-source-card no-print" data-document-create-source aria-label="PPMP setup">
        <div class="document-create-source-card__header">
            <div class="document-create-source-card__step" aria-hidden="true">1</div>
            <div>
                <p class="eyebrow">PPMP Creation</p>
                <h2>Confirm PPMP Setup</h2>
                <p>Review the office and account context before opening the official PPMP worksheet.</p>
            </div>
        </div>

        <div class="document-create-source-card__body">
            <div class="detail-grid metadata-grid">
                <div>
                    <span>Office</span>
                    <strong>{{ $ppmpOfficeName }}</strong>
                </div>
                <div>
                    <span>User ID</span>
                    <strong>{{ $user->user_id }}</strong>
                </div>
                <div>
                    <span>Role</span>
                    <strong>{{ $ppmpRoleName }}</strong>
                </div>
                <div>
                    <span>Fiscal Year</span>
                    <strong>{{ old('fiscal_year', $document->fiscal_year ?? now()->year) }}</strong>
                </div>
            </div>
        </div>

        <div class="document-create-source-card__actions">
            <a href="{{ route('head-office.ppmp.index') }}" class="document-create-secondary-action">Back to PPMP</a>
            <button type="button" class="document-create-proceed-button" data-document-create-proceed>Proceed to PPMP Worksheet</button>
        </div>

        <p class="document-create-source-error" data-document-create-error hidden>Please review the PPMP setup before proceeding.</p>
    </section>

    <div class="document-create-form" data-document-create-form>
@endif

<div class="ppmp-sticky-actions no-print" aria-label="PPMP form actions">
    <a href="{{ $document->exists ? route('head-office.ppmp.show', $document) : route('head-office.ppmp.index') }}" class="ppmp-action-secondary">Back</a>
    <button type="submit" name="save_action" value="draft">Save as Draft</button>
    <button type="submit" name="save_action" value="submit" data-confirm="PPMP created successfully. Please complete the required e-signature before submitting this document to BAC Secretariat for APP consolidation.">Continue to E-Signature</button>
    <button type="button" data-toggle-ppmp-bold aria-pressed="false" title="Bold selected PPMP row">B Bold Row</button>
    <button type="button" data-add-ppmp-row>Add Row</button>
    <button type="button" data-clear-empty-ppmp-rows>Remove Empty Rows</button>
    @if ($document->exists && Route::has('head-office.ppmp.print'))
        <a href="{{ route('head-office.ppmp.print', $document) }}" target="_blank" rel="noopener">Print</a>
    @endif
</div>

@include('head-office.ppmp._official-form', [
    'mode' => $document->exists ? 'edit' : 'create',
    'document' => $document,
    'office' => $office,
    'user' => $user,
    'items' => $items,
    'options' => $options,
])

@if ($useDocumentCreateFlow)
    </div>
@endif
