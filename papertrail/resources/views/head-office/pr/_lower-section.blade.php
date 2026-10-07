@php
    $isEditable = ($mode ?? 'readonly') === 'edit';
    $currentUser = auth()->user();
    $officeName = $document->submittingOffice?->name
        ?? $document->department_name
        ?? $currentUser?->assignedOffice?->name
        ?? $currentUser?->office
        ?? '';
    $requestingName = trim((string) ($document->submittedBy?->name ?? $currentUser?->name ?? $officeName));
    $isBacsec002PrCreator = $currentUser
        && method_exists($currentUser, 'hasBacsec002PurchaseRequestCapability')
        && $currentUser->hasBacsec002PurchaseRequestCapability();

    $defaults = $isBacsec002PrCreator ? [
        'certification' => [
            'signature' => '',
            'printed_name' => 'MEAGAN C. MATUTES',
            'designation' => '',
            'date' => '',
        ],
        'budget' => [
            'signature' => '',
            'printed_name' => 'MAVEL D. VISMANOS',
            'designation' => '',
            'date' => '',
        ],
        'requested' => [
            'signature' => '',
            'printed_name' => 'JOSEFINA M. ESCAÑO',
            'designation' => '',
            'date' => '',
        ],
        'approved' => [
            'signature' => '',
            'printed_name' => 'HON. ROD IVAN CUARES PANO',
            'designation' => '',
            'date' => '',
        ],
    ] : [
        'certification' => [
            'signature' => '',
            'printed_name' => 'MEAGAN C. MATUTES',
            'designation' => '',
            'date' => '',
        ],
        'budget' => [
            'signature' => '',
            'printed_name' => 'ENGR. AURELIO H. SUNGA, JR.',
            'designation' => '',
            'date' => '',
        ],
        'requested' => [
            'signature' => '',
            'printed_name' => $requestingName,
            'designation' => '',
            'date' => '',
        ],
        'approved' => [
            'signature' => '',
            'printed_name' => 'JESSICA MARIE G. ESCAÑO',
            'designation' => '',
            'date' => '',
        ],
    ];

    if ($document->requested_by_name) {
        $defaults['requested']['printed_name'] = $document->requested_by_name;
    }

    if ($document->requested_by_designation) {
        $defaults['requested']['designation'] = $document->requested_by_designation;
    }

    if ($document->approved_by_name) {
        $defaults['approved']['printed_name'] = $document->approved_by_name;
    }

    if ($document->approved_by_designation) {
        $defaults['approved']['designation'] = $document->approved_by_designation;
    }

    if ($document->pr_date) {
        $defaults['requested']['date'] = $document->pr_date->format('m/d/Y');
    }

    if ($document->approved_at) {
        $defaults['approved']['date'] = $document->approved_at->format('m/d/Y');
    }

    $storedSignatories = is_array($document->pr_signatories ?? null) ? $document->pr_signatories : [];
    $signatories = array_replace_recursive($defaults, $storedSignatories);

    if ($isEditable && is_array(old('signatories'))) {
        $signatories = array_replace_recursive($signatories, old('signatories'));
    }

    $columns = ['certification', 'budget', 'requested', 'approved'];
    $cellValue = fn (string $column, string $field) => $signatories[$column][$field] ?? '';
    $designationWasManuallySelected = fn (string $column): bool => filter_var($signatories[$column]['designation_manually_selected'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $submittedUserId = $document->submittedBy?->user_id;

    if (! $submittedUserId && ($document->exists ?? false) && $document->submitted_by_user_id) {
        $submittedUserId = \App\Models\User::query()->whereKey($document->submitted_by_user_id)->value('user_id');
    }

    $isBacsec002PrDocument = $isBacsec002PrCreator || $submittedUserId === 'BACSEC-002';
    $endUserSignatoryRoutingService = app(\App\Services\EndUserPrSignatoryRoutingService::class);
    $isEndUserPrDocument = ! $isBacsec002PrDocument && (
        ($document->exists ?? false)
            ? ($endUserSignatoryRoutingService->hasSignatureRouting($document) || ($isEditable && $endUserSignatoryRoutingService->isWorkflowDocument($document)))
            : ($currentUser && $endUserSignatoryRoutingService->shouldUseForUser($currentUser))
    );
    $isDesignationRoutedPrDocument = $isBacsec002PrDocument || $isEndUserPrDocument;
    $signatoryRoutingService = $isBacsec002PrDocument
        ? app(\App\Services\Bacsec002PrSignatoryRoutingService::class)
        : ($isEndUserPrDocument ? $endUserSignatoryRoutingService : null);
    $designationOptions = $signatoryRoutingService?->designationOptions() ?? [];
    $signatureStatusForColumn = fn (string $column) => $signatoryRoutingService
        ? $signatoryRoutingService->statusForColumn($document, $column)
        : ['status' => 'not_generated', 'label' => '', 'date' => null];
    $signatureSlotForColumn = fn (string $column) => $signatoryRoutingService?->signatureColumns()[$column]['slot'] ?? null;
    $signedSignaturesBySlot = ($isDesignationRoutedPrDocument && ($document->exists ?? false))
        ? app(\App\Services\SignatureRequestService::class)->signedSignaturesForDocument($document, 'purchase_request')
        : collect();
    $signedSignatureForColumn = function (string $column) use ($signatureSlotForColumn, $signedSignaturesBySlot) {
        $slot = $signatureSlotForColumn($column);

        return $slot ? $signedSignaturesBySlot->get($slot) : null;
    };
    $normalizeDesignation = function (?string $value): string {
        $normalized = strtolower(str_replace(['-', ',', '/', '\\'], ' ', (string) $value));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?: '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?: '');
    };
    $displayDesignation = function (?string $value) use ($normalizeDesignation): string {
        $designation = trim((string) $value);

        return $normalizeDesignation($designation) === $normalizeDesignation('Head of Office / End User')
            ? 'Head of Office'
            : $designation;
    };
@endphp

<table class="pr-table pr-purpose-table">
    <tbody>
        <tr>
            <td class="pr-label pr-purpose-label">Purpose:</td>
            <td colspan="5" class="pr-purpose-cell">
                @if ($isEditable)
                    <textarea class="pr-textarea pr-purpose-textarea" name="purpose" rows="2" required placeholder="State the official purpose of this Purchase Request.">{{ old('purpose', $document->purpose) }}</textarea>
                    @error('purpose')<p class="field-error">{{ $message }}</p>@enderror
                @else
                    {{ $document->purpose ?? 'N/A' }}
                @endif
            </td>
        </tr>
    </tbody>
</table>

<table class="pr-table pr-signatories pr-signatory-table">
    <colgroup>
        <col style="width: 14%">
        <col style="width: 21.5%">
        <col style="width: 21.5%">
        <col style="width: 21.5%">
        <col style="width: 21.5%">
    </colgroup>
    <tbody>
        <tr>
            <td colspan="3" class="pr-certification-cell">
                <strong>Certification (Sec. 7.8 of IRR of RA 12009)</strong>
                <span>{{ $document->certification_text ?: 'Certified that the following items are in accordance with approved Procurement Plan.' }}</span>
            </td>
            <td class="pr-signatory-heading">Requested by:</td>
            <td class="pr-signatory-heading">Approved by:</td>
        </tr>
        <tr class="pr-signature-row">
            <td class="pr-signatory-row-label">Signature:</td>
            @foreach ($columns as $column)
                <td class="pr-signatory-cell pr-signature-cell">
                    @if ($isEditable && $isDesignationRoutedPrDocument)
                        <input name="signatories[{{ $column }}][signature]" type="hidden" value="">
                    @elseif ($isEditable)
                        <input class="pr-input pr-signature-input" name="signatories[{{ $column }}][signature]" type="text" value="{{ $cellValue($column, 'signature') }}">
                    @elseif ($isDesignationRoutedPrDocument)
                        @php
                            $signedSignature = $signedSignatureForColumn($column);
                        @endphp
                        @if ($signedSignature)
                            <span class="pr-inline-signature" title="Electronically signed by {{ $signedSignature->signer_name ?? 'authorized signatory' }}">
                                @if ($signedSignature->signature_image_path)
                                    <img src="{{ route('e-signatures.image', $signedSignature) }}" alt="{{ $signedSignature->signer_name ?? 'Electronic' }} signature">
                                @else
                                    <span class="pr-inline-signature-text">{{ $signedSignature->typed_signature_name ?: $signedSignature->signer_name }}</span>
                                @endif
                            </span>
                        @else
                            &nbsp;
                        @endif
                    @else
                        {{ $cellValue($column, 'signature') }}
                    @endif
                </td>
            @endforeach
        </tr>
        <tr>
            <td class="pr-signatory-row-label">Printed Name:</td>
            @foreach ($columns as $column)
                <td class="pr-signatory-cell">
                    @if ($isEditable)
                        <textarea class="pr-textarea pr-signatory-input pr-signatory-multiline" name="signatories[{{ $column }}][printed_name]" rows="2">{{ $cellValue($column, 'printed_name') }}</textarea>
                    @else
                        <span class="pr-signatory-text">{{ $cellValue($column, 'printed_name') }}</span>
                    @endif
                </td>
            @endforeach
        </tr>
        <tr>
            <td class="pr-signatory-row-label">Designation:</td>
            @foreach ($columns as $column)
                <td class="pr-signatory-cell">
                    @if ($isEditable && $isDesignationRoutedPrDocument)
                        @php
                            $selectedDesignation = $designationWasManuallySelected($column) ? $cellValue($column, 'designation') : '';
                        @endphp
                        <input type="hidden" name="signatories[{{ $column }}][designation_manually_selected]" value="{{ $selectedDesignation !== '' ? '1' : '0' }}" data-pr-signatory-designation-manual>
                        <select class="pr-input pr-signatory-input pr-signatory-select" name="signatories[{{ $column }}][designation]">
                            <option value="" @selected($selectedDesignation === '')>-- Select role --</option>
                            @foreach ($designationOptions as $designationOption)
                                <option value="{{ $designationOption }}" @selected($normalizeDesignation($selectedDesignation) === $normalizeDesignation($designationOption))>
                                    {{ $displayDesignation($designationOption) }}
                                </option>
                            @endforeach
                        </select>
                    @elseif ($isEditable)
                        <textarea class="pr-textarea pr-signatory-input pr-signatory-multiline" name="signatories[{{ $column }}][designation]" rows="2">{{ $cellValue($column, 'designation') }}</textarea>
                    @else
                        <span class="pr-signatory-text">
                            {{ $isDesignationRoutedPrDocument
                                ? ($designationWasManuallySelected($column) ? $displayDesignation($cellValue($column, 'designation')) : '')
                                : $displayDesignation($cellValue($column, 'designation')) }}
                        </span>
                    @endif
                </td>
            @endforeach
        </tr>
        <tr>
            <td class="pr-signatory-row-label">Date:</td>
            @foreach ($columns as $column)
                <td class="pr-signatory-cell">
                    @php
                        $signatureStatus = $signatureStatusForColumn($column);
                        $signatureDate = $signatureStatus['date'] ?? null;
                        $dateValue = $signatureDate ? $signatureDate->format('m/d/Y') : $cellValue($column, 'date');

                        if ($isDesignationRoutedPrDocument && ! $signatureDate && $cellValue($column, 'signature_status') !== 'signed') {
                            $dateValue = '';
                        }
                    @endphp
                    @if ($isEditable && $isDesignationRoutedPrDocument)
                        @if ($dateValue)
                            <span class="pr-signature-date-placeholder">{{ $dateValue }}</span>
                        @endif
                        <input name="signatories[{{ $column }}][date]" type="hidden" value="{{ $dateValue }}">
                    @elseif ($isEditable)
                        <input class="pr-input pr-signatory-input pr-signatory-date-input" name="signatories[{{ $column }}][date]" type="text" value="{{ $cellValue($column, 'date') }}">
                    @else
                        {{ $dateValue }}
                    @endif
                </td>
            @endforeach
        </tr>
    </tbody>
</table>
