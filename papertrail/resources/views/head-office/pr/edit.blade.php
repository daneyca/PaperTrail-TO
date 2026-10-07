@extends('layouts.dashboard')

@section('title', 'Edit Purchase Request | PaperTrail')

@section('content')
    @php
        $officeName = $office?->name ?? $user->assignedOffice?->name ?? $user->office ?? 'your office';
        $canChooseRequestingOffice = $canSelectRequestingOffice ?? false;
        $selectedRequestingOfficeId = old('requesting_office_id', $office?->id);
        $hasBacsec002PrCapability = method_exists($user, 'hasBacsec002PurchaseRequestCapability') && $user->hasBacsec002PurchaseRequestCapability();
        $usesPrSignatureRouting = $hasBacsec002PrCapability || $user->roleSlug() === 'head-office';
        $roleLabel = $hasBacsec002PrCapability ? 'BAC Secretariat' : 'Head of Office / End User';
        $status = $document->status ?: 'pr_draft';
        $submitButtonLabel = $usesPrSignatureRouting ? 'Submit for Signatures' : 'Submit for PR Number';
        $submitConfirmMessage = $usesPrSignatureRouting
            ? 'Generate signature requests for this Purchase Request?'
            : 'Submit this Purchase Request to PR Numbering Staff?';
        $statusLabels = [
            'pr_draft' => 'Draft',
            'pr_pending_signatories' => 'Pending Signatures',
            'pr_signatories_completed' => 'Ready for PR Number',
            'pending_pr_number_assignment' => 'Pending PR Number',
            'returned_by_pr_numbering_staff' => 'Returned',
            'pr_submitted' => 'Submitted',
            'returned_by_bac_secretariat' => 'Returned',
            'returned_by_budget' => 'Returned',
            'returned_by_accounting' => 'Returned',
            'returned_by_bac_member' => 'Returned',
            'returned_by_bac_chair' => 'Returned',
            'returned_by_approving_authority' => 'Returned',
            'pr_returned' => 'Returned',
        ];
        $statusLabel = $statusLabels[$status] ?? str($status)->replace('_', ' ')->title();
    @endphp

    <form id="purchaseRequestForm" method="POST" action="{{ route('head-office.pr.update', $document) }}" class="pr-workspace pr-workspace-form pr-edit-workspace" data-purchase-request-form>
        <input type="hidden" name="form_action" id="purchaseRequestFormAction" value="{{ old('form_action', 'save_draft') }}">

        <section class="pr-page-header no-print">
            <div>
                <p class="pr-page-kicker">Head Office Purchase Request</p>
                <h1 class="pr-page-title">Edit Purchase Request Draft</h1>
            </div>
        </section>

        @if ($canChooseRequestingOffice)
            <section class="pr-app-reference-filter no-print" aria-label="Requesting office">
                <label for="requestingOfficeId">
                    Requesting Office
                    <select id="requestingOfficeId" name="requesting_office_id" class="pr-context-select" data-requesting-office-select>
                        @foreach (($requestingOffices ?? collect()) as $requestingOffice)
                            <option
                                value="{{ $requestingOffice->id }}"
                                data-office-name="{{ $requestingOffice->name }}"
                                @selected((string) $selectedRequestingOfficeId === (string) $requestingOffice->id)
                            >
                                {{ $requestingOffice->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('requesting_office_id')<p class="field-error">{{ $message }}</p>@enderror
                </label>
            </section>
        @endif

        @if ($errors->any())
            <div class="session-alert session-alert-error pr-form-errors no-print" role="alert">
                <strong>Please correct the following errors:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="pr-action-toolbar no-print" aria-label="Purchase Request actions">
            <div class="pr-toolbar-group">
                <a href="{{ route('head-office.pr.show', $document) }}" class="btn-pr-secondary">Back</a>
                <a href="#supporting-files" class="btn-pr-secondary">Supporting Files</a>
            </div>
            <div class="pr-toolbar-group">
                <button type="button" class="btn-pr-secondary" data-add-pr-item>Add Row</button>
                <button type="button" class="btn-pr-secondary" data-remove-last-pr-item>Remove Last Row</button>
                <button type="button" id="removeEmptyPrRowsBtn" class="btn-pr-secondary" data-clear-empty-pr-items>Remove Empty Rows</button>
                <button type="submit" form="purchaseRequestForm" name="form_action" value="save_draft" class="btn-pr-dark" data-pr-draft-action>Save Draft</button>
                <button type="submit" form="purchaseRequestForm" name="form_action" value="submit_for_pr_number" class="btn-pr-primary" id="submitForPrNumberBtn" data-submit-pr-number data-submit-confirm="{{ $submitConfirmMessage }}">{{ $submitButtonLabel }}</button>
            </div>
        </section>

        @include('head-office.pr._app-reference-selector', [
            'document' => $document,
            'appReferenceItems' => $appReferenceItems ?? collect(),
            'showReferenceDescription' => false,
        ])

        @include('head-office.pr._budget-guard', ['appBudgetInfo' => $appBudgetInfo ?? null])

        <div class="pr-preview-area">
            @include('head-office.pr._form')
        </div>
    </form>

    <div id="pr-signatures">
        @include('head-office.pr._signatory-tracking-panel', ['document' => $document])
    </div>

    <x-documents.attachments-panel
        id="supporting-files"
        :document="$document"
        document-type="purchase_request"
        :can-upload="true"
        title="Supporting Files"
    />
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const officeSelect = document.querySelector('[data-requesting-office-select]');
            const departmentInput = document.querySelector('[data-pr-department-name]');

            if (!officeSelect || !departmentInput) {
                return;
            }

            const syncDepartmentName = () => {
                const selectedOption = officeSelect.options[officeSelect.selectedIndex];
                departmentInput.value = selectedOption?.dataset.officeName || departmentInput.value;
            };

            officeSelect.addEventListener('change', syncDepartmentName);
            syncDepartmentName();
        });
    </script>
@endpush
