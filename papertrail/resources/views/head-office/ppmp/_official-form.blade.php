@php
    $mode = $mode ?? 'show';
    $editable = in_array($mode, ['create', 'edit'], true);
    $document->loadMissing(['submittingOffice', 'submittedBy', 'preparedBy']);

    $oldItems = old('items');
    $items = collect(is_array($oldItems) ? $oldItems : ($items ?? $document->ppmpItems ?? []));
    $minimumRows = $mode === 'create' ? max(18, $items->count()) : $items->count();
    while ($items->count() < $minimumRows) {
        $items->push([]);
    }

    $officeName = ($office ?? null)?->name
        ?? $document->submittingOffice?->name
        ?? $document->department_name
        ?? ($user ?? null)?->office
        ?? 'MUNICIPAL BUDGET OFFICE';

    $preparedName = old('prepared_by_name', $document->prepared_by_name ?: ($document->preparedBy?->name ?? $document->submittedBy?->name ?? ($user ?? null)?->name));
    $preparedPosition = $document->preparedBy?->position ?? $document->submittedBy?->position ?? ($user ?? null)?->position;
    $submittedName = old('submitted_by_name', $document->submitted_by_name ?: ($document->submittedBy?->name ?? ($user ?? null)?->name));
    $submittedPosition = $document->submittedBy?->position ?? ($user ?? null)?->position;
    $signatureSlots = isset($signatureSlots)
        ? collect($signatureSlots)
        : ($document->exists ? app(\App\Services\SignatureRequestService::class)->signedSignaturesForDocument($document, 'ppmp') : collect());
    $combinedSignature = $signatureSlots->get('prepared_and_submitted_by')
        ?? $signatureSlots->get('prepared_by')
        ?? $signatureSlots->get('submitted_by');
    $preparedSignature = $combinedSignature;
    $submittedSignature = $combinedSignature;
    $signatureDate = function ($signature): string {
        $signedAt = $signature?->signed_at;

        return $signedAt
            ? $signedAt->copy()->timezone('Asia/Manila')->format('m/d/Y')
            : '____________________';
    };
    $planType = old('ppmp_plan_type', $document->ppmp_plan_type ?: 'indicative');
    $ppmpNo = old('ppmp_no', $document->ppmp_no ?: $document->tracking_number);
    $fiscalYear = old('fiscal_year', $document->fiscal_year ?: now()->year);

    $options = $options ?? [
        'planTypes' => ['indicative' => 'Indicative', 'final' => 'Final'],
        'projectTypes' => ['Goods', 'Infrastructure', 'Consulting Services'],
        'procurementModes' => ['Small Value', 'Small Value Procurement', 'Competitive Bidding', 'Negotiated Procurement', 'Direct Acquisition', 'Shopping', 'Other'],
        'preProcurementOptions' => ['Yes', 'No', 'N/A'],
        'fundSources' => ['General Fund', 'Trust Fund', 'Special Purpose Fund', 'Other'],
    ];

    $cell = function ($item, string $key, $fallback = '') {
        return data_get($item, $key, $fallback);
    };

    $fieldValue = function ($item, int $index, string $key, $fallback = '') use ($cell) {
        return old("items.$index.$key", $cell($item, $key, $fallback));
    };

    $readText = function ($value, bool $upper = false) {
        $value = trim((string) $value);
        return $upper ? str($value)->upper() : $value;
    };

    $isSubtotalText = function ($value): bool {
        $normalized = trim(preg_replace('/\s+/', ' ', str_replace(':', '', (string) $value)));

        return preg_match('/^(tot\.?|total)(\s+(budget|amount))?$/i', $normalized) === 1;
    };

    $isSubtotalItem = function ($item) use ($cell, $isSubtotalText): bool {
        return collect([
            $cell($item, 'general_description'),
            $cell($item, 'project_type', $cell($item, 'category')),
            $cell($item, 'quantity_size'),
            $cell($item, 'procurement_mode', $cell($item, 'recommended_mode')),
            $cell($item, 'pre_procurement_conference'),
            $cell($item, 'start_procurement_activity'),
            $cell($item, 'end_procurement_activity'),
            $cell($item, 'expected_delivery_period'),
            $cell($item, 'source_of_funds'),
            $cell($item, 'supporting_documents', $cell($item, 'attached_supporting_documents')),
            $cell($item, 'remarks'),
        ])->contains(fn ($value) => $isSubtotalText($value));
    };

    $itemBudgetValue = function ($item) use ($cell): float {
        $value = $cell($item, 'estimated_budget', $cell($item, 'estimated_total_cost'));

        return filled($value) ? max((float) preg_replace('/[^0-9.\-]/', '', (string) $value), 0) : 0.0;
    };

    $subtotalRowCount = 0;
    $currentSectionTotal = 0.0;
    foreach ($items as $item) {
        if ($isSubtotalItem($item)) {
            $subtotalRowCount += 1;
            $currentSectionTotal = 0.0;
            continue;
        }

        $currentSectionTotal += $itemBudgetValue($item);
    }

    $showFinalTotalRow = $subtotalRowCount === 0 || $currentSectionTotal > 0;
    $displayedFinalTotal = $subtotalRowCount > 0 && $currentSectionTotal > 0
        ? $currentSectionTotal
        : (float) $document->total_amount;
@endphp

@if ($editable)
    <input type="hidden" name="title" value="{{ old('title', $document->title ?: 'Project Procurement Plan') }}">
    <input type="hidden" name="description" value="{{ old('description', $document->description ?: 'Office PPMP for Fiscal Year ' . $fiscalYear) }}">
    <input type="hidden" name="priority" value="{{ old('priority', $document->priority ?: 'normal') }}">
@endif

<section class="ppmp-entry-scroll {{ $mode === 'print' ? 'is-print' : '' }}">
    <article class="ppmp-sheet ppmp-entry-sheet ppmp-official-document" id="ppmpPrintPaper" data-ppmp-builder data-ppmp-mode="{{ $mode }}">
        <header class="ppmp-official-header">
            <div class="ppmp-logo-slot">
                @if (file_exists(public_path('images/logos/lgu-logo.png')))
                    <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Logo">
                @endif
            </div>

            <div class="ppmp-header-text">
                <p>Republic of the Philippines</p>
                <p>Province of Southern Leyte</p>
                <strong>MUNICIPALITY OF TOMAS OPPUS</strong>
            </div>

            <div class="ppmp-logo-slot ppmp-bagong-slot">
                @if (file_exists(public_path('images/logos/bagongpilipinas.jpg')))
                    <img src="{{ asset('images/logos/bagongpilipinas.jpg') }}" alt="Bagong Pilipinas">
                @else
                    <span>BAGONG PILIPINAS</span>
                @endif
            </div>
        </header>

        <div class="ppmp-title-row ppmp-entry-title">
            <h1>
                PROJECT PROCUREMENT PLAN (PPMP) NO.
                @if ($editable)
                    <input name="ppmp_no" type="text" value="{{ $ppmpNo }}" aria-label="PPMP Number">
                @else
                    <span>{{ $ppmpNo ?: '__________' }}</span>
                @endif
            </h1>
            @error('ppmp_no')<p class="field-error">{{ $message }}</p>@enderror

            <div class="ppmp-plan-checks ppmp-entry-checks" aria-label="Plan type">
                @foreach ($options['planTypes'] as $value => $label)
                    @if ($editable)
                        <label class="ppmp-check-option">
                            <input type="radio" name="ppmp_plan_type" value="{{ $value }}" @checked($planType === $value)>
                            <span></span>
                            {{ strtoupper($label) }}
                        </label>
                    @else
                        <span>[{{ $planType === $value ? 'x' : ' ' }}] {{ strtoupper($label) }}</span>
                    @endif
                @endforeach
            </div>
            @error('ppmp_plan_type')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="ppmp-meta-lines ppmp-entry-meta">
            <p>
                Fiscal Year :
                @if ($editable)
                    <input name="fiscal_year" type="number" min="2020" max="2100" value="{{ $fiscalYear }}" aria-label="Fiscal Year">
                @else
                    <strong>{{ $fiscalYear }}</strong>
                @endif
            </p>
            @error('fiscal_year')<p class="field-error">{{ $message }}</p>@enderror

            <p>End-User or Implementing Unit : <strong>{{ $officeName }}</strong></p>
        </div>

        @error('items')<p class="field-error ppmp-items-error">{{ $message }}</p>@enderror

        <table class="ppmp-official-table ppmp-entry-table">
            <colgroup>
                <col class="ppmp-col-1">
                <col class="ppmp-col-2">
                <col class="ppmp-col-3">
                <col class="ppmp-col-4">
                <col class="ppmp-col-5">
                <col class="ppmp-col-6">
                <col class="ppmp-col-7">
                <col class="ppmp-col-8">
                <col class="ppmp-col-9">
                <col class="ppmp-col-10">
                <col class="ppmp-col-11">
                <col class="ppmp-col-12">
            </colgroup>
            <thead>
                <tr class="ppmp-group-row">
                    <th colspan="5">PROCUREMENT PROJECT DETAILS</th>
                    <th colspan="3">PROJECTED TIMELINE (MM/YYYY)</th>
                    <th colspan="2">FUNDING DETAILS</th>
                    <th rowspan="2">ATTACHED SUPPORTING DOCUMENTS</th>
                    <th rowspan="2">REMARKS</th>
                </tr>
                <tr class="ppmp-heading-row">
                    <th>General Description and Objective of the Project to be Procured</th>
                    <th>Type of Project to be Procured (whether Goods, Infrastructure, and Consulting Services)</th>
                    <th>Quantity and Size of the Project to be Procured</th>
                    <th>Recommended Mode of Procurement</th>
                    <th>Pre-Procurement Conference, if applicable (Yes/No)</th>
                    <th>Start of Procurement Activity</th>
                    <th>End of Procurement Activity</th>
                    <th>Expected Delivery/Implementation Period</th>
                    <th>Source of Funds</th>
                    <th>Estimated Budget / Authorized Budget Allocation</th>
                </tr>
                <tr class="ppmp-column-row">
                    @for ($column = 1; $column <= 12; $column++)
                        <th>Column {{ $column }}</th>
                    @endfor
                </tr>
            </thead>
            <tbody data-ppmp-rows>
                @php $renderedSectionTotal = 0.0; @endphp
                @foreach ($items as $index => $item)
                    @php
                        $generalDescription = $fieldValue($item, $index, 'general_description');
                        $projectType = $fieldValue($item, $index, 'project_type');
                        $quantitySize = $fieldValue($item, $index, 'quantity_size');
                        $procurementMode = $fieldValue($item, $index, 'procurement_mode', $cell($item, 'recommended_mode'));
                        $preProc = $fieldValue($item, $index, 'pre_procurement_conference');
                        $startActivity = $fieldValue($item, $index, 'start_procurement_activity');
                        $endActivity = $fieldValue($item, $index, 'end_procurement_activity');
                        $deliveryPeriod = $fieldValue($item, $index, 'expected_delivery_period');
                        $sourceOfFunds = $fieldValue($item, $index, 'source_of_funds');
                        $budget = $fieldValue($item, $index, 'estimated_budget', $cell($item, 'estimated_total_cost'));
                        $budgetValue = is_numeric((string) $budget) && (float) $budget > 0 ? $budget : '';
                        $supporting = $fieldValue($item, $index, 'supporting_documents', $cell($item, 'attached_supporting_documents'));
                        $remarks = $fieldValue($item, $index, 'remarks');
                        if (trim((string) $generalDescription) === '' && is_numeric((string) $quantitySize) && (float) $quantitySize === 0.0) {
                            $quantitySize = '';
                        }
                        if (trim((string) $generalDescription) === '' && strcasecmp(trim((string) $projectType), 'Goods') === 0 && trim((string) $quantitySize) === '') {
                            $projectType = '';
                        }
                        $isBold = filter_var(old("items.$index.is_bold", $cell($item, 'is_bold', false)), FILTER_VALIDATE_BOOL);
                        $hasReadOnlyContent = trim((string) $generalDescription . (string) $projectType . (string) $quantitySize . (string) $procurementMode . (string) $preProc . (string) $startActivity . (string) $endActivity . (string) $deliveryPeriod . (string) $sourceOfFunds . (string) $budget . (string) $supporting . (string) $remarks) !== '';
                        $hasItemColumns = trim((string) $projectType . (string) $quantitySize . (string) $procurementMode . (string) $preProc . (string) $startActivity . (string) $endActivity . (string) $deliveryPeriod . (string) $sourceOfFunds . (string) $budget . (string) $supporting . (string) $remarks) !== '';
                        $isSubtotalLike = collect([$generalDescription, $projectType, $quantitySize, $procurementMode, $preProc, $startActivity, $endActivity, $deliveryPeriod, $sourceOfFunds, $supporting, $remarks])
                            ->contains(fn ($value) => $isSubtotalText($value));
                        if ($isSubtotalLike) {
                            $budgetValue = $renderedSectionTotal > 0 ? number_format($renderedSectionTotal, 2, '.', '') : '';
                            $budget = $budgetValue;
                            $renderedSectionTotal = 0.0;
                        } else {
                            $renderedSectionTotal += is_numeric((string) $budget) ? max((float) $budget, 0) : 0.0;
                        }
                        $isSectionLike = $hasReadOnlyContent && ! $hasItemColumns && str($generalDescription)->upper()->toString() === trim((string) $generalDescription);
                    @endphp
                    <tr class="ppmp-data-row ppmp-entry-row {{ $isBold ? 'ppmp-bold-row' : '' }} {{ $isSubtotalLike ? 'ppmp-subtotal-row' : '' }} {{ $isSectionLike ? 'ppmp-section-row' : '' }} {{ $hasReadOnlyContent && ! $hasItemColumns && ! $isSubtotalLike ? 'ppmp-text-only-row' : '' }}">
                        <td class="ppmp-project-title">
                            @if ($editable)
                                <input type="hidden" name="items[{{ $index }}][is_bold]" value="{{ $isBold ? '1' : '0' }}" data-ppmp-bold-value>
                                <textarea name="items[{{ $index }}][general_description]" rows="1" data-ppmp-column="general_description">{{ $generalDescription }}</textarea>
                                @error("items.$index.general_description")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasReadOnlyContent ? $readText($generalDescription) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <textarea name="items[{{ $index }}][project_type]" rows="1" data-ppmp-column="project_type">{{ $projectType }}</textarea>
                                @error("items.$index.project_type")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasReadOnlyContent ? $readText($projectType) : '' }}
                            @endif
                        </td>
                        <td class="ppmp-quantity-cell">
                            @if ($editable)
                                <textarea name="items[{{ $index }}][quantity_size]" rows="1" data-ppmp-column="quantity_size">{{ $quantitySize }}</textarea>
                                @error("items.$index.quantity_size")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasReadOnlyContent ? $readText($quantitySize) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <textarea name="items[{{ $index }}][procurement_mode]" rows="1" data-ppmp-column="procurement_mode">{{ $procurementMode }}</textarea>
                                @error("items.$index.procurement_mode")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasReadOnlyContent ? $readText($procurementMode) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <textarea name="items[{{ $index }}][pre_procurement_conference]" rows="1" data-ppmp-column="pre_procurement_conference">{{ $preProc }}</textarea>
                            @else
                                {{ $hasReadOnlyContent ? $readText($preProc) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <textarea name="items[{{ $index }}][start_procurement_activity]" rows="1" data-ppmp-column="start_procurement_activity">{{ $startActivity }}</textarea>
                                @error("items.$index.start_procurement_activity")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasReadOnlyContent ? $readText($startActivity) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <textarea name="items[{{ $index }}][end_procurement_activity]" rows="1" data-ppmp-column="end_procurement_activity">{{ $endActivity }}</textarea>
                                @error("items.$index.end_procurement_activity")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasReadOnlyContent ? $readText($endActivity) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <textarea name="items[{{ $index }}][expected_delivery_period]" rows="1" data-ppmp-column="expected_delivery_period">{{ $deliveryPeriod }}</textarea>
                                @error("items.$index.expected_delivery_period")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasReadOnlyContent ? $readText($deliveryPeriod) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <textarea name="items[{{ $index }}][source_of_funds]" rows="1" data-ppmp-column="source_of_funds">{{ $sourceOfFunds }}</textarea>
                                @error("items.$index.source_of_funds")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasReadOnlyContent ? $readText($sourceOfFunds) : '' }}
                            @endif
                        </td>
                        <td class="ppmp-money-cell">
                            @if ($editable)
                                <input name="items[{{ $index }}][estimated_budget]" type="text" inputmode="decimal" value="{{ $budgetValue }}" data-estimated-budget data-ppmp-column="estimated_budget">
                                @error("items.$index.estimated_budget")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasReadOnlyContent && is_numeric((string) $budget) && (float) $budget > 0 ? number_format((float) $budget, 2) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <textarea name="items[{{ $index }}][supporting_documents]" rows="1" data-ppmp-column="supporting_documents">{{ $supporting }}</textarea>
                            @else
                                {{ $hasReadOnlyContent ? $readText($supporting) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <textarea name="items[{{ $index }}][remarks]" rows="1" data-ppmp-column="remarks">{{ $remarks }}</textarea>
                                <button type="button" class="ppmp-cell-remove no-print" data-remove-ppmp-row>Remove</button>
                            @else
                                {{ $hasReadOnlyContent ? $readText($remarks) : '' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr class="ppmp-total-row {{ $showFinalTotalRow ? '' : 'is-hidden' }}">
                    <td colspan="9">TOTAL BUDGET:</td>
                    <td class="ppmp-money-cell"><span data-ppmp-total>{{ number_format($displayedFinalTotal, 2) }}</span></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>

        <section class="ppmp-signature-grid ppmp-entry-signatures">
            <div class="ppmp-signature-block">
                <p>Prepared by:</p>
                <div class="ppmp-electronic-signature-line" aria-label="Prepared By electronic signature">
                    @if ($preparedSignature)
                        @if ($preparedSignature->signature_image_path)
                            <img src="{{ route('e-signatures.image', $preparedSignature) }}" alt="{{ $preparedSignature->signer_name ?? 'Prepared By' }} signature">
                        @else
                            <span class="ppmp-electronic-signature-text">{{ $preparedSignature->typed_signature_name ?: $preparedSignature->signer_name }}</span>
                        @endif
                    @endif
                </div>
                @if ($editable)
                    <input name="prepared_by_name" type="text" value="{{ $preparedName }}" aria-label="Prepared by printed name">
                    @error('prepared_by_name')<p class="field-error">{{ $message }}</p>@enderror
                @else
                    <strong>{{ $preparedName ?: '' }}</strong>
                @endif
                <span>{{ $preparedPosition ?: '' }}</span>
                <em>Signature Over Printed Name</em>
                <em>Position/Designation</em>
                <em>(End-User or Implementing Unit)</em>
                <p>Date: <span class="ppmp-date-line">{{ $signatureDate($preparedSignature) }}</span></p>
            </div>

            <div class="ppmp-signature-block">
                <p>Submitted by:</p>
                <div class="ppmp-electronic-signature-line" aria-label="Submitted By electronic signature">
                    @if ($submittedSignature)
                        @if ($submittedSignature->signature_image_path)
                            <img src="{{ route('e-signatures.image', $submittedSignature) }}" alt="{{ $submittedSignature->signer_name ?? 'Submitted By' }} signature">
                        @else
                            <span class="ppmp-electronic-signature-text">{{ $submittedSignature->typed_signature_name ?: $submittedSignature->signer_name }}</span>
                        @endif
                    @endif
                </div>
                @if ($editable)
                    <input name="submitted_by_name" type="text" value="{{ $submittedName }}" aria-label="Submitted by printed name">
                    @error('submitted_by_name')<p class="field-error">{{ $message }}</p>@enderror
                @else
                    <strong>{{ $submittedName ?: '' }}</strong>
                @endif
                <span>{{ $submittedPosition ?: '' }}</span>
                <em>Signature Over Printed Name</em>
                <em>Position/Designation</em>
                <em>(Head of the End-User or Implementing Unit)</em>
                <p>Date: <span class="ppmp-date-line">{{ $signatureDate($submittedSignature) }}</span></p>
            </div>
        </section>
    </article>
</section>

@if ($editable)
    <template id="ppmpRowTemplate">
        <tr class="ppmp-data-row ppmp-entry-row">
            <td class="ppmp-project-title">
                <input type="hidden" name="items[__INDEX__][is_bold]" value="0" data-ppmp-bold-value>
                <textarea name="items[__INDEX__][general_description]" rows="1" data-ppmp-column="general_description"></textarea>
            </td>
            <td><textarea name="items[__INDEX__][project_type]" rows="1" data-ppmp-column="project_type"></textarea></td>
            <td class="ppmp-quantity-cell"><textarea name="items[__INDEX__][quantity_size]" rows="1" data-ppmp-column="quantity_size"></textarea></td>
            <td><textarea name="items[__INDEX__][procurement_mode]" rows="1" data-ppmp-column="procurement_mode"></textarea></td>
            <td><textarea name="items[__INDEX__][pre_procurement_conference]" rows="1" data-ppmp-column="pre_procurement_conference"></textarea></td>
            <td><textarea name="items[__INDEX__][start_procurement_activity]" rows="1" data-ppmp-column="start_procurement_activity"></textarea></td>
            <td><textarea name="items[__INDEX__][end_procurement_activity]" rows="1" data-ppmp-column="end_procurement_activity"></textarea></td>
            <td><textarea name="items[__INDEX__][expected_delivery_period]" rows="1" data-ppmp-column="expected_delivery_period"></textarea></td>
            <td><textarea name="items[__INDEX__][source_of_funds]" rows="1" data-ppmp-column="source_of_funds"></textarea></td>
            <td class="ppmp-money-cell"><input name="items[__INDEX__][estimated_budget]" type="text" inputmode="decimal" data-estimated-budget data-ppmp-column="estimated_budget"></td>
            <td><textarea name="items[__INDEX__][supporting_documents]" rows="1" data-ppmp-column="supporting_documents"></textarea></td>
            <td>
                <textarea name="items[__INDEX__][remarks]" rows="1" data-ppmp-column="remarks"></textarea>
                <button type="button" class="ppmp-cell-remove no-print" data-remove-ppmp-row>Remove</button>
            </td>
        </tr>
    </template>

    <script>
        (() => {
            const builder = document.querySelector('[data-ppmp-builder]');
            if (!builder) return;

            const rows = builder.querySelector('[data-ppmp-rows]');
            const template = document.querySelector('#ppmpRowTemplate');
            const totalTarget = builder.querySelector('[data-ppmp-total]');
            const addButtons = document.querySelectorAll('[data-add-ppmp-row]');
            const clearButtons = document.querySelectorAll('[data-clear-empty-ppmp-rows]');
            const boldButton = document.querySelector('[data-toggle-ppmp-bold]');
            const money = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const pasteColumns = [
                'general_description',
                'project_type',
                'quantity_size',
                'procurement_mode',
                'pre_procurement_conference',
                'start_procurement_activity',
                'end_procurement_activity',
                'expected_delivery_period',
                'source_of_funds',
                'estimated_budget',
                'supporting_documents',
                'remarks',
            ];
            let nextIndex = rows.querySelectorAll('.ppmp-entry-row').length;
            let activeRow = null;

            function dataRows() {
                return [...rows.querySelectorAll('.ppmp-entry-row')];
            }

            function rowHasTypedContent(row) {
                return pasteColumns.some((name) => {
                    const field = fieldForColumn(row, name);
                    return field && String(field.value || '').trim() !== '';
                });
            }

            function rowValues(row) {
                return pasteColumns.map((name) => String(fieldForColumn(row, name)?.value || '').trim());
            }

            function autosizeField(field) {
                if (!field || field.tagName !== 'TEXTAREA') {
                    return;
                }

                field.style.height = 'auto';
                field.style.height = `${Math.max(field.scrollHeight, 30)}px`;
            }

            function autosizeRow(row) {
                row?.querySelectorAll('textarea[data-ppmp-column]').forEach(autosizeField);
            }

            function autosizeAllRows() {
                dataRows().forEach(autosizeRow);
            }

            function isSubtotalText(value) {
                return /^(tot\.?|total)(\s+(budget|amount))?$/i.test(String(value || '').replace(/:/g, '').replace(/\s+/g, ' ').trim());
            }

            function isSubtotalRow(row) {
                return rowValues(row).some(isSubtotalText);
            }

            function isTextOnlyRow(row) {
                const values = rowValues(row);
                return values[0] !== '' && values.slice(1).every((value) => value === '');
            }

            function updateRowPresentation(row) {
                row.classList.toggle('ppmp-subtotal-row', isSubtotalRow(row));
                row.classList.toggle('ppmp-text-only-row', !isSubtotalRow(row) && isTextOnlyRow(row));
                row.classList.toggle('ppmp-section-row', !isSubtotalRow(row) && isTextOnlyRow(row) && rowValues(row)[0] === rowValues(row)[0].toUpperCase());
            }

            function setActiveRow(row) {
                if (!row) return;

                activeRow = row;
                boldButton?.setAttribute('aria-pressed', row.classList.contains('ppmp-bold-row') ? 'true' : 'false');
            }

            function toggleRowBold(row = activeRow) {
                if (!row) return;

                const isBold = !row.classList.contains('ppmp-bold-row');
                row.classList.toggle('ppmp-bold-row', isBold);

                const value = row.querySelector('[data-ppmp-bold-value]');
                if (value) {
                    value.value = isBold ? '1' : '0';
                }

                setActiveRow(row);
            }

            function numericValue(value) {
                const parsed = Number(String(value || '').replace(/,/g, ''));

                return Number.isFinite(parsed) ? parsed : 0;
            }

            function recalculateSectionSubtotals() {
                let sectionTotal = 0;
                let subtotalRows = 0;

                dataRows().forEach((row) => {
                    const budgetField = fieldForColumn(row, 'estimated_budget');

                    if (isSubtotalRow(row)) {
                        subtotalRows += 1;
                        if (budgetField) {
                            budgetField.value = sectionTotal > 0 ? sectionTotal.toFixed(2) : '';
                        }

                        sectionTotal = 0;
                        return;
                    }

                    sectionTotal += numericValue(budgetField?.value);
                });

                return { sectionTotal, subtotalRows };
            }

            function recalculateTotal() {
                let total = 0;
                const sectionSummary = recalculateSectionSubtotals();

                rows.querySelectorAll('[data-estimated-budget]').forEach((field) => {
                    if (isSubtotalRow(field.closest('.ppmp-entry-row'))) {
                        return;
                    }

                    total += numericValue(field.value);
                });

                if (totalTarget) {
                    const finalTotalRow = totalTarget.closest('.ppmp-total-row');
                    const hasTrailingSection = sectionSummary.subtotalRows === 0 || sectionSummary.sectionTotal > 0;

                    finalTotalRow?.classList.toggle('is-hidden', !hasTrailingSection);
                    totalTarget.textContent = money.format(sectionSummary.subtotalRows > 0 ? sectionSummary.sectionTotal : total);
                }
            }

            function addRow() {
                const row = template.content.firstElementChild.cloneNode(true);
                row.innerHTML = row.innerHTML.replaceAll('__INDEX__', String(nextIndex));
                nextIndex += 1;
                rows.insertBefore(row, rows.querySelector('.ppmp-total-row'));
                recalculateTotal();
                autosizeRow(row);

                return row;
            }

            function ensureRow(rowIndex) {
                while (dataRows().length <= rowIndex) {
                    addRow();
                }

                return dataRows()[rowIndex] || null;
            }

            function fieldForColumn(row, column) {
                return row?.querySelector(`[data-ppmp-column="${column}"]`) || null;
            }

            function fieldRowIndex(field) {
                return dataRows().indexOf(field.closest('.ppmp-entry-row'));
            }

            function normalizePasteValue(value, column) {
                const clean = String(value || '').replace(/\u00a0/g, ' ').trim();

                if (column !== 'estimated_budget') {
                    return clean;
                }

                return clean.replace(/php/ig, '').replace(/,/g, '').replace(/[^0-9.\-]/g, '');
            }

            function isHeaderPasteRow(values) {
                const filled = values.map((value) => String(value || '').trim()).filter(Boolean);

                if (filled.length === 0) {
                    return true;
                }

                return filled.every((value) => /^column\s+\d+$/i.test(value))
                    || filled.some((value) => /^general description/i.test(value))
                    || filled.some((value) => /^type of project/i.test(value))
                    || filled.some((value) => /^estimated budget/i.test(value));
            }

            function setFieldValue(field, value) {
                if (!field) return;

                field.value = value;
                field.dispatchEvent(new Event('input', { bubbles: true }));
                updateRowPresentation(field.closest('.ppmp-entry-row'));
                autosizeField(field);
            }

            function focusField(rowIndex, columnIndex) {
                const row = dataRows()[rowIndex];
                const field = fieldForColumn(row, pasteColumns[columnIndex]);

                field?.focus();
                if (field?.select) {
                    field.select();
                }
            }

            addButtons.forEach((button) => button.addEventListener('click', addRow));

            boldButton?.addEventListener('click', () => {
                toggleRowBold();
            });

            clearButtons.forEach((button) => button.addEventListener('click', () => {
                const dataRows = [...rows.querySelectorAll('.ppmp-entry-row')];
                let remainingRows = dataRows.length;

                dataRows.forEach((row) => {
                    if (remainingRows > 1 && !rowHasTypedContent(row)) {
                        row.remove();
                        remainingRows -= 1;
                    }
                });
                recalculateTotal();
                autosizeAllRows();
            }));

            builder.addEventListener('input', (event) => {
                const row = event.target.closest('.ppmp-entry-row');

                if (row) {
                    setActiveRow(row);
                    updateRowPresentation(row);
                    autosizeField(event.target);
                }

                if (event.target.matches('[data-estimated-budget], [data-ppmp-column]')) {
                    recalculateTotal();
                }
            });

            builder.addEventListener('focusin', (event) => {
                setActiveRow(event.target.closest('.ppmp-entry-row'));
            });

            builder.addEventListener('keydown', (event) => {
                if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 'b') {
                    return;
                }

                const row = event.target.closest('.ppmp-entry-row');
                if (!row) return;

                event.preventDefault();
                toggleRowBold(row);
            });

            builder.addEventListener('paste', (event) => {
                const currentField = event.target.closest('[data-ppmp-column]');

                if (!currentField || !builder.contains(currentField)) {
                    return;
                }

                const text = event.clipboardData?.getData('text/plain') || '';

                if (!text.includes('\t') && !text.includes('\n')) {
                    return;
                }

                event.preventDefault();

                const startRow = Math.max(0, fieldRowIndex(currentField));
                const startColumn = Math.max(0, pasteColumns.indexOf(currentField.dataset.ppmpColumn));
                const pastedRows = text
                    .replace(/\r/g, '')
                    .split('\n')
                    .map((line) => line.split('\t'))
                    .filter((values) => !isHeaderPasteRow(values));

                pastedRows.forEach((values, rowOffset) => {
                    const row = ensureRow(startRow + rowOffset);

                    values.forEach((value, columnOffset) => {
                        const columnIndex = startColumn + columnOffset;

                        if (columnIndex >= pasteColumns.length) {
                            return;
                        }

                        const column = pasteColumns[columnIndex];
                        setFieldValue(fieldForColumn(row, column), normalizePasteValue(value, column));
                    });
                });

                recalculateTotal();

                if (pastedRows.length > 0) {
                    const lastValues = pastedRows[pastedRows.length - 1] || [];
                    focusField(startRow + pastedRows.length - 1, Math.min(pasteColumns.length - 1, startColumn + Math.max(0, lastValues.length - 1)));
                }
            });

            builder.addEventListener('click', (event) => {
                if (!event.target.matches('[data-remove-ppmp-row]')) {
                    return;
                }

                const dataRows = rows.querySelectorAll('.ppmp-entry-row');
                if (dataRows.length === 1) {
                    dataRows[0].querySelectorAll('input, textarea').forEach((field) => {
                        field.value = '';
                        autosizeField(field);
                    });
                } else {
                    event.target.closest('tr')?.remove();
                }

                recalculateTotal();
            });

            recalculateTotal();
            dataRows().forEach(updateRowPresentation);
            autosizeAllRows();
            window.addEventListener('resize', autosizeAllRows);
            setActiveRow(dataRows()[0] || null);
        })();
    </script>
@else
    <script>
        (() => {
            const paginate = () => {
            const source = document.getElementById('ppmpPrintPaper');
            if (!source || source.dataset.ppmpMode === 'create' || source.dataset.ppmpMode === 'edit' || source.dataset.ppmpPaginated === '1') {
                return;
            }

            const table = source.querySelector('.ppmp-official-table');
            const sourceBody = table?.querySelector('tbody[data-ppmp-rows]');
            const rows = [...(sourceBody?.querySelectorAll('.ppmp-entry-row') || [])];
            const totalRow = sourceBody?.querySelector('.ppmp-total-row');
            const signatures = source.querySelector('.ppmp-signature-grid');
            const repeatBlocks = [
                source.querySelector('.ppmp-official-header'),
                source.querySelector('.ppmp-title-row'),
                source.querySelector('.ppmp-meta-lines'),
            ].filter(Boolean);

            if (!table || !sourceBody || rows.length === 0 || !totalRow || !signatures || repeatBlocks.length === 0) {
                return;
            }

            const host = document.createElement('div');
            host.className = 'ppmp-paginated-document';
            host.style.visibility = 'hidden';
            source.before(host);
            source.style.display = 'none';

            const makeTable = () => {
                const clone = table.cloneNode(false);
                const colgroup = table.querySelector('colgroup');
                const thead = table.querySelector('thead');
                const tbody = document.createElement('tbody');
                tbody.setAttribute('data-ppmp-rows', '');

                if (colgroup) clone.appendChild(colgroup.cloneNode(true));
                if (thead) clone.appendChild(thead.cloneNode(true));
                clone.appendChild(tbody);

                return { table: clone, tbody };
            };

            const makePage = (isFinalPage) => {
                const page = document.createElement('article');
                page.className = `${source.className} ppmp-paginated-page`;
                page.removeAttribute('id');
                page.dataset.ppmpPaginated = '1';

                repeatBlocks.forEach((block) => page.appendChild(block.cloneNode(true)));

                const builtTable = makeTable();
                page.appendChild(builtTable.table);

                let totalClone = null;
                let signatureClone = null;

                if (isFinalPage) {
                    totalClone = totalRow.cloneNode(true);
                    builtTable.tbody.appendChild(totalClone);
                    signatureClone = signatures.cloneNode(true);
                    page.appendChild(signatureClone);
                }

                host.appendChild(page);

                return {
                    page,
                    tbody: builtTable.tbody,
                    totalClone,
                    signatureClone,
                };
            };

            const overflows = (page) => page.scrollHeight > page.clientHeight + 2;
            const remaining = rows.map((row) => row.cloneNode(true));
            const pages = [];

            while (remaining.length > 0) {
                const candidate = makePage(true);
                let addedToCandidate = 0;

                while (remaining.length > 0) {
                    const row = remaining.shift();
                    candidate.tbody.insertBefore(row, candidate.totalClone);

                    if (overflows(candidate.page)) {
                        row.remove();
                        remaining.unshift(row);
                        break;
                    }

                    addedToCandidate += 1;
                }

                if (remaining.length === 0 && !overflows(candidate.page)) {
                    pages.push(candidate.page);
                    break;
                }

                candidate.totalClone?.remove();
                candidate.signatureClone?.remove();

                while (remaining.length > 0) {
                    const row = remaining.shift();
                    candidate.tbody.appendChild(row);

                    if (overflows(candidate.page)) {
                        row.remove();
                        remaining.unshift(row);
                        break;
                    }

                    addedToCandidate += 1;
                }

                if (addedToCandidate === 0 && remaining.length > 0) {
                    candidate.tbody.appendChild(remaining.shift());
                }

                if (remaining.length === 0) {
                    const finalTotal = totalRow.cloneNode(true);
                    const finalSignature = signatures.cloneNode(true);
                    candidate.tbody.appendChild(finalTotal);
                    candidate.page.appendChild(finalSignature);

                    if (!overflows(candidate.page)) {
                        pages.push(candidate.page);
                        break;
                    }

                    finalTotal.remove();
                    finalSignature.remove();

                    const finalPage = makePage(true);
                    let movedRows = 0;

                    const currentRows = [...candidate.tbody.querySelectorAll('.ppmp-entry-row')];
                    const row = currentRows[currentRows.length - 1];

                    if (row) {
                        row.remove();
                        finalPage.tbody.insertBefore(row, finalPage.totalClone);

                        if (overflows(finalPage.page)) {
                            row.remove();
                            candidate.tbody.appendChild(row);
                        } else {
                            movedRows = 1;
                        }
                    }

                    if (movedRows === 0) {
                        finalPage.remove();
                    } else {
                        pages.push(candidate.page);
                        pages.push(finalPage.page);
                        break;
                    }
                }

                pages.push(candidate.page);
            }

            if (!host.querySelector('.ppmp-signature-grid')) {
                const finalPage = makePage(true);
                pages.push(finalPage.page);
            }

            pages.forEach((page, index) => {
                page.dataset.ppmpPage = String(index + 1);
                page.dataset.ppmpPageTotal = String(pages.length);
                if (index === 0) {
                    page.id = source.id;
                }
            });

            host.style.visibility = '';
            source.remove();
            };

            const runWhenReady = () => {
                const run = () => requestAnimationFrame(() => window.setTimeout(paginate, 80));

                if (document.fonts?.ready) {
                    document.fonts.ready.then(run).catch(run);
                    return;
                }

                run();
            };

            if (document.readyState === 'complete') {
                runWhenReady();
            } else {
                window.addEventListener('load', runWhenReady, { once: true });
            }
        })();
    </script>
@endif
