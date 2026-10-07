@php
    $mode = $mode ?? 'show';
    $editable = in_array($mode, ['create', 'edit'], true);
    $oldItems = old('items');
    $rows = collect(is_array($oldItems) ? $oldItems : ($items ?? $ppmp->items ?? []));
    $minimumRows = $mode === 'create' ? max(3, $rows->count()) : $rows->count();

    while ($rows->count() < $minimumRows) {
        $rows->push([]);
    }

    $options = $options ?? [
        'planTypes' => ['indicative' => 'Indicative', 'final' => 'Final'],
        'projectTypes' => ['Goods', 'Infrastructure', 'Consulting Services'],
        'procurementModes' => ['Small Value Procurement', 'Competitive Bidding', 'Negotiated Procurement', 'Direct Acquisition', 'Shopping', 'Other'],
        'preProcurementOptions' => ['Yes', 'No', 'N/A'],
        'fundSources' => ['General Fund', 'Trust Fund', 'Special Purpose Fund', 'Other'],
    ];

    $planType = old('plan_type', $ppmp->plan_type ?: 'indicative');
    $fiscalYear = old('fiscal_year', $ppmp->fiscal_year ?: now()->year);
    $endUserUnit = old('end_user_unit', $ppmp->end_user_unit);

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

    $budgetText = function ($value) {
        $amount = (float) str_replace(',', '', (string) $value);
        return filled($value) && $amount > 0 ? number_format($amount, 2) : '';
    };
@endphp

<section class="ppmp-entry-scroll {{ $mode === 'print' ? 'is-print' : '' }}">
    <article class="ppmp-sheet ppmp-entry-sheet ppmp-official-document {{ $mode === 'print' ? 'is-print' : '' }}" id="ppmpPrintPaper" data-ppmp-builder>
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
                    <input name="ppmp_no" type="text" value="{{ old('ppmp_no', $ppmp->ppmp_no) }}" aria-label="PPMP Number">
                @else
                    <span>{{ $ppmp->ppmp_no ?: '__________' }}</span>
                @endif
            </h1>
            @error('ppmp_no')<p class="field-error">{{ $message }}</p>@enderror

            <div class="ppmp-plan-checks ppmp-entry-checks" aria-label="Plan type">
                @foreach ($options['planTypes'] as $value => $label)
                    @if ($editable)
                        <label class="ppmp-check-option">
                            <input type="radio" name="plan_type" value="{{ $value }}" @checked($planType === $value)>
                            <span></span>
                            {{ strtoupper($label) }}
                        </label>
                    @else
                        <span>[{{ $planType === $value ? 'x' : ' ' }}] {{ strtoupper($label) }}</span>
                    @endif
                @endforeach
            </div>
            @error('plan_type')<p class="field-error">{{ $message }}</p>@enderror
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

            <p>
                End-User or Implementing Unit :
                @if ($editable)
                    <input name="end_user_unit" type="text" value="{{ $endUserUnit }}" aria-label="End-User or Implementing Unit">
                @else
                    <strong>{{ $endUserUnit }}</strong>
                @endif
            </p>
            @error('end_user_unit')<p class="field-error">{{ $message }}</p>@enderror
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
                @foreach ($rows as $index => $item)
                    @php
                        $generalDescription = $fieldValue($item, $index, 'general_description');
                        $projectType = $fieldValue($item, $index, 'project_type');
                        $quantitySize = $fieldValue($item, $index, 'quantity_size');
                        $recommendedMode = $fieldValue($item, $index, 'recommended_mode', $cell($item, 'procurement_mode'));
                        $preProc = $fieldValue($item, $index, 'pre_procurement_conference');
                        $startActivity = $fieldValue($item, $index, 'start_procurement_activity');
                        $endActivity = $fieldValue($item, $index, 'end_procurement_activity');
                        $deliveryPeriod = $fieldValue($item, $index, 'expected_delivery_period');
                        $sourceOfFunds = $fieldValue($item, $index, 'source_of_funds');
                        $estimatedBudget = $fieldValue($item, $index, 'estimated_budget', $cell($item, 'estimated_total_cost'));
                        $supportingDocuments = $fieldValue($item, $index, 'supporting_documents', $cell($item, 'attached_supporting_documents'));
                        $remarks = $fieldValue($item, $index, 'remarks');
                        if (trim((string) $generalDescription) === '' && is_numeric((string) $quantitySize) && (float) $quantitySize === 0.0) {
                            $quantitySize = '';
                        }
                        if (trim((string) $generalDescription) === '' && strcasecmp(trim((string) $projectType), 'Goods') === 0 && trim((string) $quantitySize) === '') {
                            $projectType = '';
                        }
                        $hasContent = trim((string) $generalDescription . (string) $projectType . (string) $quantitySize . (string) $recommendedMode . (string) $preProc . (string) $startActivity . (string) $endActivity . (string) $deliveryPeriod . (string) $sourceOfFunds . (string) $estimatedBudget . (string) $supportingDocuments . (string) $remarks) !== '';
                        $hasItemColumns = trim((string) $projectType . (string) $quantitySize . (string) $recommendedMode . (string) $preProc . (string) $startActivity . (string) $endActivity . (string) $deliveryPeriod . (string) $sourceOfFunds . (string) $estimatedBudget . (string) $supportingDocuments . (string) $remarks) !== '';
                        $isSectionLike = $hasContent && ! $hasItemColumns && str($generalDescription)->upper()->toString() === trim((string) $generalDescription);
                    @endphp
                    <tr class="ppmp-data-row ppmp-entry-row {{ $isSectionLike ? 'ppmp-section-row' : '' }} {{ $hasContent && ! $hasItemColumns ? 'ppmp-text-only-row' : '' }}">
                        <td class="ppmp-project-title">
                            @if ($editable)
                                <textarea name="items[{{ $index }}][general_description]" rows="1">{{ $generalDescription }}</textarea>
                                @error("items.$index.general_description")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasContent ? $readText($generalDescription) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <input name="items[{{ $index }}][project_type]" type="text" value="{{ $projectType }}">
                                @error("items.$index.project_type")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasContent ? $readText($projectType) : '' }}
                            @endif
                        </td>
                        <td class="ppmp-quantity-cell">
                            @if ($editable)
                                <textarea name="items[{{ $index }}][quantity_size]" rows="1">{{ $quantitySize }}</textarea>
                                @error("items.$index.quantity_size")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasContent ? $readText($quantitySize) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <input name="items[{{ $index }}][recommended_mode]" type="text" value="{{ $recommendedMode }}">
                                @error("items.$index.recommended_mode")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasContent ? $readText($recommendedMode) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <input name="items[{{ $index }}][pre_procurement_conference]" type="text" value="{{ $preProc }}">
                            @else
                                {{ $hasContent ? $readText($preProc) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <input name="items[{{ $index }}][start_procurement_activity]" type="text" value="{{ $startActivity }}">
                                @error("items.$index.start_procurement_activity")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasContent ? $readText($startActivity) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <input name="items[{{ $index }}][end_procurement_activity]" type="text" value="{{ $endActivity }}">
                                @error("items.$index.end_procurement_activity")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasContent ? $readText($endActivity) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <input name="items[{{ $index }}][expected_delivery_period]" type="text" value="{{ $deliveryPeriod }}">
                                @error("items.$index.expected_delivery_period")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasContent ? $readText($deliveryPeriod) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <input name="items[{{ $index }}][source_of_funds]" type="text" value="{{ $sourceOfFunds }}">
                                @error("items.$index.source_of_funds")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasContent ? $readText($sourceOfFunds) : '' }}
                            @endif
                        </td>
                        <td class="ppmp-money-cell">
                            @if ($editable)
                                <input name="items[{{ $index }}][estimated_budget]" type="number" min="0" step="0.01" value="{{ $estimatedBudget }}" data-estimated-budget>
                                @error("items.$index.estimated_budget")<p class="field-error">{{ $message }}</p>@enderror
                            @else
                                {{ $hasContent ? $budgetText($estimatedBudget) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <textarea name="items[{{ $index }}][supporting_documents]" rows="1">{{ $supportingDocuments }}</textarea>
                            @else
                                {{ $hasContent ? $readText($supportingDocuments) : '' }}
                            @endif
                        </td>
                        <td>
                            @if ($editable)
                                <textarea name="items[{{ $index }}][remarks]" rows="1">{{ $remarks }}</textarea>
                                <button type="button" class="ppmp-cell-remove no-print" data-remove-ppmp-row>Remove</button>
                            @else
                                {{ $hasContent ? $readText($remarks) : '' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr class="ppmp-total-row">
                    <td colspan="9">TOTAL BUDGET:</td>
                    <td class="ppmp-money-cell"><span data-ppmp-total>{{ number_format((float) ($ppmp->total_budget ?? 0), 2) }}</span></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>

        <section class="ppmp-signature-grid ppmp-entry-signatures">
            <div class="ppmp-signature-block">
                <p>Prepared by:</p>
                @if ($editable)
                    <input name="prepared_by_name" type="text" value="{{ old('prepared_by_name', $ppmp->prepared_by_name) }}" aria-label="Prepared by name">
                    <input name="prepared_by_position" type="text" value="{{ old('prepared_by_position', $ppmp->prepared_by_position) }}" aria-label="Prepared by position">
                @else
                    <strong>{{ $ppmp->prepared_by_name ?: '' }}</strong>
                    <span>{{ $ppmp->prepared_by_position ?: '' }}</span>
                @endif
                <em>Signature Over Printed Name</em>
                <em>Position/Designation</em>
                <em>(End-User or Implementing Unit)</em>
                <p>
                    Date:
                    @if ($editable)
                        <input name="prepared_date" type="date" value="{{ old('prepared_date', $ppmp->prepared_date?->format('Y-m-d')) }}" aria-label="Prepared date">
                    @else
                        <span class="ppmp-date-line">{{ $ppmp->prepared_date?->format('m/d/Y') ?: '____________________' }}</span>
                    @endif
                </p>
            </div>

            <div class="ppmp-signature-block">
                <p>Submitted by:</p>
                @if ($editable)
                    <input name="submitted_by_name" type="text" value="{{ old('submitted_by_name', $ppmp->submitted_by_name) }}" aria-label="Submitted by name">
                    <input name="submitted_by_position" type="text" value="{{ old('submitted_by_position', $ppmp->submitted_by_position) }}" aria-label="Submitted by position">
                @else
                    <strong>{{ $ppmp->submitted_by_name ?: '' }}</strong>
                    <span>{{ $ppmp->submitted_by_position ?: '' }}</span>
                @endif
                <em>Signature Over Printed Name</em>
                <em>Position/Designation</em>
                <em>(Head of the End-User or Implementing Unit)</em>
                <p>
                    Date:
                    @if ($editable)
                        <input name="submitted_date" type="date" value="{{ old('submitted_date', $ppmp->submitted_date?->format('Y-m-d')) }}" aria-label="Submitted date">
                    @else
                        <span class="ppmp-date-line">{{ $ppmp->submitted_date?->format('m/d/Y') ?: '____________________' }}</span>
                    @endif
                </p>
            </div>
        </section>
    </article>
</section>

@if ($editable)
    <template id="ppmpRowTemplate">
        <tr class="ppmp-data-row ppmp-entry-row">
            <td class="ppmp-project-title"><textarea name="items[__INDEX__][general_description]" rows="1"></textarea></td>
            <td><input name="items[__INDEX__][project_type]" type="text"></td>
            <td class="ppmp-quantity-cell"><textarea name="items[__INDEX__][quantity_size]" rows="1"></textarea></td>
            <td><input name="items[__INDEX__][recommended_mode]" type="text"></td>
            <td><input name="items[__INDEX__][pre_procurement_conference]" type="text"></td>
            <td><input name="items[__INDEX__][start_procurement_activity]" type="text"></td>
            <td><input name="items[__INDEX__][end_procurement_activity]" type="text"></td>
            <td><input name="items[__INDEX__][expected_delivery_period]" type="text"></td>
            <td><input name="items[__INDEX__][source_of_funds]" type="text"></td>
            <td class="ppmp-money-cell"><input name="items[__INDEX__][estimated_budget]" type="number" min="0" step="0.01" data-estimated-budget></td>
            <td><textarea name="items[__INDEX__][supporting_documents]" rows="1"></textarea></td>
            <td>
                <textarea name="items[__INDEX__][remarks]" rows="1"></textarea>
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
            const money = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            let nextIndex = rows.querySelectorAll('.ppmp-entry-row').length;

            function rowHasTypedContent(row) {
                const fieldNames = [
                    'general_description',
                    'project_type',
                    'quantity_size',
                    'recommended_mode',
                    'pre_procurement_conference',
                    'start_procurement_activity',
                    'end_procurement_activity',
                    'expected_delivery_period',
                    'source_of_funds',
                    'estimated_budget',
                    'supporting_documents',
                    'remarks'
                ];

                return fieldNames.some((name) => {
                    const field = row.querySelector(`[name$="[${name}]"]`);
                    return field && String(field.value || '').trim() !== '';
                });
            }

            function recalculateTotal() {
                let total = 0;

                rows.querySelectorAll('[data-estimated-budget]').forEach((field) => {
                    total += parseFloat(field.value || '0') || 0;
                });

                if (totalTarget) {
                    totalTarget.textContent = money.format(total);
                }
            }

            function addRow() {
                const row = template.content.firstElementChild.cloneNode(true);
                row.innerHTML = row.innerHTML.replaceAll('__INDEX__', String(nextIndex));
                nextIndex += 1;
                rows.insertBefore(row, rows.querySelector('.ppmp-total-row'));
                recalculateTotal();
            }

            addButtons.forEach((button) => button.addEventListener('click', addRow));

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
            }));

            builder.addEventListener('input', (event) => {
                if (event.target.matches('[data-estimated-budget]')) {
                    recalculateTotal();
                }
            });

            builder.addEventListener('click', (event) => {
                if (!event.target.matches('[data-remove-ppmp-row]')) {
                    return;
                }

                const dataRows = rows.querySelectorAll('.ppmp-entry-row');
                if (dataRows.length === 1) {
                    dataRows[0].querySelectorAll('input, textarea').forEach((field) => field.value = '');
                } else {
                    event.target.closest('tr')?.remove();
                }

                recalculateTotal();
            });

            recalculateTotal();
        })();
    </script>
@endif
