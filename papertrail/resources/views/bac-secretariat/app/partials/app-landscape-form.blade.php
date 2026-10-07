@php
    $mode = $mode ?? 'show';
    $isEditable = in_array($mode, ['create', 'edit'], true);
    $signatoriesLocked = (bool) ($signatoriesLocked ?? false);
    $field = fn (string $name, mixed $default = '') => old($name, filled($app->{$name} ?? null) ? $app->{$name} : $default);
    $money = fn ($value) => filled($value) ? number_format((float) preg_replace('/[^0-9.\-]/', '', (string) $value), 2) : '';
    $plain = fn ($value, string $fallback = '') => filled($value) ? (string) $value : $fallback;
    $text = fn ($value, string $fallback = '') => $plain($value, $fallback);

    $categories = [
        'general_requirements' => 'General Requirements',
        'miscellaneous_items' => 'Miscellaneous Items (for Direct Acquisition only) Sec. 32.2 of RA No. 12009',
        'cse' => 'Common Use Supplies and Equipment (CSE) to be purchased from PS-DBM (kindly indicate the summary total amount only)',
    ];

    $minimumRows = [
        'general_requirements' => 2,
        'miscellaneous_items' => 3,
        'cse' => 3,
    ];

    $oldItemsJson = old('items_json');
    $decodedOldItems = $oldItemsJson !== null ? json_decode($oldItemsJson, true) : null;

    $normalizeItem = function (array|object $source) {
        $value = fn (string $key, mixed $default = null) => data_get($source, $key, $default);

        return [
            'source_ppmp_document_id' => $value('source_ppmp_document_id'),
            'source_ppmp_item_id' => $value('source_ppmp_item_id'),
            'category' => $value('category', 'general_requirements') ?: 'general_requirements',
            'project_title' => $value('project_title') ?: $value('procurement_program_project'),
            'end_user_unit' => $value('end_user_unit') ?: $value('pmo_end_user'),
            'general_description' => $value('general_description'),
            'mode_of_procurement' => $value('mode_of_procurement'),
            'early_procurement_activity' => $value('early_procurement_activity'),
            'bid_evaluation_criteria' => $value('bid_evaluation_criteria'),
            'start_procurement_activity' => $value('start_procurement_activity') ?: $value('ads_post_ib_rei'),
            'end_procurement_activity' => $value('end_procurement_activity') ?: $value('contract_signing'),
            'source_of_funds' => $value('source_of_funds'),
            'estimated_budget' => $value('estimated_budget') ?: $value('estimated_total'),
            'procurement_strategy_or_tools' => $value('procurement_strategy_or_tools'),
            'remarks' => $value('remarks'),
        ];
    };

    $items = collect(is_array($decodedOldItems) ? $decodedOldItems : null)->map($normalizeItem);

    if ($items->isEmpty()) {
        if (is_array($app->items_json)) {
            $items = collect($app->items_json)->map($normalizeItem);
        } elseif ($app->relationLoaded('items') || $app->exists) {
            $items = $app->items->map($normalizeItem);
        }
    }

    $itemsByCategory = $items->groupBy(fn ($item) => $item['category'] ?: 'general_requirements');
    $budgetValue = fn ($item) => (float) preg_replace('/[^0-9.\-]/', '', (string) ($item['estimated_budget'] ?? 0));
    $totalEstimatedBudget = $items->sum($budgetValue);
    $totalEpaBudget = $items
        ->filter(fn ($item) => strcasecmp(trim((string) ($item['early_procurement_activity'] ?? '')), 'yes') === 0)
        ->sum($budgetValue);
    $totalCseBudget = ($itemsByCategory['cse'] ?? collect())->sum($budgetValue);
    $totalDisplay = fn ($stored, $computed) => filled($stored) ? $money($stored) : ($items->isNotEmpty() ? $money($computed) : '');
    $lockedPpmpIds = collect($lockedPpmpIds ?? [])->filter()->map(fn ($id) => (string) (int) $id)->values();
    $isLockedItem = fn (array $item): bool => filled($item['source_ppmp_document_id'] ?? null)
        && $lockedPpmpIds->contains((string) (int) $item['source_ppmp_document_id']);

    $planType = old('plan_type', $app->plan_type ?? 'indicative');
    $updateVersionNo = old('update_version_no', $app->update_version_no);
    $logoPath = public_path('images/logos/lgu-logo.png');
    $bagongPath = public_path('images/logos/bagongpilipinas.jpg');

    $defaultSignatories = [
        'prepared_by' => ['label' => 'Prepared By:', 'name' => 'JOBELLE A. SOLER', 'title' => '', 'office' => ''],
        'recommended_by' => ['label' => 'Recommended By:', 'authority' => 'By the Authority of the Bids and Awards Com.', 'name' => 'EDMAR PETER T. TAMBIS', 'title' => '', 'office' => ''],
        'approved_by' => ['label' => 'Approved By:', 'name' => 'AURELIO H. SUNGA, JR.', 'title' => '', 'office' => ''],
    ];
    $signatoryDesignationOptions = [
        'BAC Secretariat' => 'Bids and Awards Committee Secretariat',
        'BAC Chair' => 'Bids and Awards Committee',
        'BAC Vice Chairperson' => 'Bids and Awards Committee',
        'BAC Member' => 'Bids and Awards Committee',
        'Municipal Budget Officer' => 'Municipal Budget Office',
        'Municipal Accountant' => 'Municipal Accounting Office',
        'Municipal Mayor' => 'Office of the Municipal Mayor',
        'Head of Office / End User' => '',
        'Head of the Procuring Entity' => 'Office of the Municipal Mayor',
        'PR Numbering Staff' => '',
    ];
    $legacySignatoryDesignations = [
        'Budget Officer' => 'Municipal Budget Officer',
        'Accounting Officer' => 'Municipal Accountant',
    ];

    $storedSignatories = collect(old('signatories_json') ? json_decode(old('signatories_json'), true) : ($app->signatories_json ?? []));
    $signatoryValue = function (string $key, string $column) use ($storedSignatories, $defaultSignatories) {
        return data_get($storedSignatories, "{$key}.{$column}", $defaultSignatories[$key][$column] ?? '');
    };
    $signatoryTitleWasManuallySelected = function (string $key) use ($storedSignatories): bool {
        return filter_var(data_get($storedSignatories, "{$key}.title_manually_selected", false), FILTER_VALIDATE_BOOLEAN);
    };
    $signatoryTitle = function (string $key) use ($signatoryValue, $signatoryTitleWasManuallySelected, $legacySignatoryDesignations) {
        if (! $signatoryTitleWasManuallySelected($key)) {
            return '';
        }

        $title = (string) $signatoryValue($key, 'title');

        return $legacySignatoryDesignations[$title] ?? $title;
    };
    $signatoryOffice = function (string $key) use ($signatoryTitle, $signatoryValue) {
        return filled($signatoryTitle($key)) ? $signatoryValue($key, 'office') : '';
    };
    $signatureSlots = isset($signatureSlots) ? collect($signatureSlots) : collect();
    $slotSignature = fn (string $key) => $signatureSlots->get($key);
    $signatureDate = function ($signature): string {
        $signedAt = $signature?->signed_at;

        return $signedAt
            ? $signedAt->copy()->timezone('Asia/Manila')->format('m/d/Y')
            : '________________________';
    };

    $cellValue = fn (array $item, string $column) => $text($item[$column] ?? '');
@endphp

@if ($isEditable)
    <input type="hidden" name="document_html" id="app_document_html">
    <input type="hidden" name="document_text" id="app_document_text">
    <input type="hidden" name="items_json" id="app_items_json">
    <input type="hidden" name="signatories_json" id="app_signatories_json">
    <input type="hidden" name="plan_type" value="{{ $planType }}">
    <input type="hidden" name="update_version_no" value="{{ $updateVersionNo }}">
    @foreach (['app_number', 'app_no', 'fiscal_year', 'title', 'office_id', 'office_name', 'province', 'municipality', 'remarks'] as $hiddenField)
        <input type="hidden" name="{{ $hiddenField }}" data-app-hidden-field="{{ $hiddenField }}" value="{{ old($hiddenField, $app->{$hiddenField} ?? '') }}">
    @endforeach
@endif

<div class="app-document-wrap">
    <section class="app-landscape-page">
        <div id="appEditor" class="app-document">
            <header class="app-official-header">
                <div class="app-logo-slot">
                    @if (file_exists($logoPath))
                        <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Logo">
                    @endif
                </div>
                <div class="app-header-text">
                    <div>Republic of the Philippines</div>
                    <div class="app-editable-field" data-field="province">{{ $text($field('province', 'Province of Southern Leyte')) }}</div>
                    <div class="app-municipality app-editable-field" data-field="municipality">{{ $text($field('municipality', 'MUNICIPALITY OF TOMAS OPPUS')) }}</div>
                </div>
                <div class="app-logo-slot app-bagong-slot">
                    @if (file_exists($bagongPath))
                        <img src="{{ asset('images/logos/bagongpilipinas.jpg') }}" alt="Bagong Pilipinas Logo">
                    @endif
                </div>
            </header>

            <h1 class="app-official-title">ANNUAL PROCUREMENT PLAN</h1>

            <div class="app-plan-type-row">
                @foreach (['indicative' => 'INDICATIVE', 'final' => 'FINAL', 'update' => 'UPDATE'] as $value => $label)
                    <label class="app-plan-option">
                        <i class="app-plan-box {{ $planType === $value ? 'is-checked' : '' }}" aria-hidden="true"></i>
                        <span>{{ $label }}</span>
                        @if ($value === 'update')
                            <em>(Version No. 
                                <span>{{ $text($updateVersionNo, '___') }}</span>
                            )</em>
                        @endif
                    </label>
                @endforeach
            </div>

            <table class="app-table app-official-table" aria-label="Annual Procurement Plan">
                <colgroup>
                    <col style="width:12%">
                    <col style="width:8%">
                    <col style="width:11%">
                    <col style="width:7.2%">
                    <col style="width:8.5%">
                    <col style="width:10%">
                    <col style="width:6.6%">
                    <col style="width:7%">
                    <col style="width:7%">
                    <col style="width:8.5%">
                    <col style="width:7.2%">
                    <col style="width:7%">
                </colgroup>
                <thead>
                    <tr class="app-group-row">
                        <th colspan="4">PROCUREMENT PROJECT DETAILS</th>
                        <th colspan="4">PROJECTED TIMELINE (MM/YYYY)</th>
                        <th colspan="2">FUNDING DETAILS</th>
                        <th rowspan="2">PROCUREMENT<br>STRATEGY OR<br>TOOLS</th>
                        <th rowspan="2">REMARKS<br>(Other relevant<br>description<br>procurement<br>project, if any)</th>
                    </tr>
                    <tr class="app-heading-row">
                        <th>Project Title</th>
                        <th>End-User or<br>Implementing<br>Unit</th>
                        <th>General Description<br>of the Project</th>
                        <th>Mode of<br>Procurement</th>
                        <th>To be covered by<br>an Early<br>Procurement<br>Activity (yes/No)</th>
                        <th>Criteria for Bid<br>Evaluation (<br>Including<br>Sustainability and<br>Domestic Bidder<br>Preference)</th>
                        <th>Start of<br>Procurement<br>Activity</th>
                        <th>End of<br>Procurement<br>Activity</th>
                        <th>Source of<br>Funds</th>
                        <th>Estimated<br>Budget/<br>Authorized<br>Budget Allocation</th>
                    </tr>
                    <tr class="app-column-row">
                        @for ($column = 1; $column <= 12; $column++)
                            <th>Column {{ $column }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $categoryKey => $categoryLabel)
                        @php
                            $categoryItems = collect($itemsByCategory[$categoryKey] ?? [])->values();
                            $rowCount = max($minimumRows[$categoryKey] ?? 1, $categoryItems->count());
                        @endphp
                        <tr class="app-category-row" data-category-section="{{ $categoryKey }}">
                            <td colspan="12">{{ $categoryLabel }}</td>
                        </tr>
                        @for ($index = 0; $index < $rowCount; $index++)
                            @php
                                $item = $categoryItems->get($index, []);
                                $isLockedRow = $isLockedItem($item);
                            @endphp
                            <tr
                                class="app-item-row {{ $isLockedRow ? 'app-item-row--locked' : '' }}"
                                data-category="{{ $categoryKey }}"
                                @if ($isLockedRow) data-app-locked-row="true" aria-readonly="true" @endif
                                @if (filled($item['source_ppmp_document_id'] ?? null)) data-source-ppmp-document-id="{{ $item['source_ppmp_document_id'] }}" @endif
                                @if (filled($item['source_ppmp_item_id'] ?? null)) data-source-ppmp-item-id="{{ $item['source_ppmp_item_id'] }}" @endif
                            >
                                <td class="app-cell app-left app-project-cell" data-column="project_title">{{ $cellValue($item, 'project_title') }}</td>
                                <td class="app-cell app-center" data-column="end_user_unit">{{ $cellValue($item, 'end_user_unit') }}</td>
                                <td class="app-cell app-center" data-column="general_description">{{ $cellValue($item, 'general_description') }}</td>
                                <td class="app-cell app-center" data-column="mode_of_procurement">{{ $cellValue($item, 'mode_of_procurement') }}</td>
                                <td class="app-cell app-center" data-column="early_procurement_activity">{{ $cellValue($item, 'early_procurement_activity') }}</td>
                                <td class="app-cell app-center" data-column="bid_evaluation_criteria">{{ $cellValue($item, 'bid_evaluation_criteria') }}</td>
                                <td class="app-cell app-center" data-column="start_procurement_activity">{{ $cellValue($item, 'start_procurement_activity') }}</td>
                                <td class="app-cell app-center" data-column="end_procurement_activity">{{ $cellValue($item, 'end_procurement_activity') }}</td>
                                <td class="app-cell app-center" data-column="source_of_funds">{{ $cellValue($item, 'source_of_funds') }}</td>
                                <td class="app-cell app-right app-money" data-column="estimated_budget">{{ $text($money($item['estimated_budget'] ?? null)) }}</td>
                                <td class="app-cell app-center" data-column="procurement_strategy_or_tools">{{ $cellValue($item, 'procurement_strategy_or_tools') }}</td>
                                <td class="app-cell app-left app-remarks-cell" data-column="remarks">{{ $cellValue($item, 'remarks') }}</td>
                            </tr>
                        @endfor
                    @endforeach
                </tbody>
            </table>

            <section class="app-note-total-row" aria-label="APP totals">
                <p>Note: Insert additional rows as necessary</p>
                <div class="app-total-summary">
                    <div><span>Total Amount of Estimated Budget for EPA Projects:</span><strong data-app-epa-total>{{ $totalDisplay($app->total_epa_budget ?? null, $totalEpaBudget) }}</strong></div>
                    <div><span>Total Amount of CSE to be purchased from PS-DBM:</span><strong data-app-cse-total>{{ $totalDisplay($app->total_cse_budget ?? null, $totalCseBudget) }}</strong></div>
                    <div><span>Total Amount of Estimated Budget :</span><strong data-app-grand-total>{{ $totalDisplay($app->total_estimated_budget ?? null, $totalEstimatedBudget) }}</strong></div>
                </div>
            </section>

            <section class="app-signatories" aria-label="APP signatures">
                @foreach ($defaultSignatories as $key => $signatory)
                    @php $signature = $slotSignature($key); @endphp
                    <div class="app-signatory">
                        <div class="app-signatory-label">{{ $signatory['label'] }}</div>
                        @if (filled($signatory['authority'] ?? null))
                            <div class="app-signatory-authority">{{ $signatory['authority'] }}</div>
                        @endif
                        <div class="app-electronic-signature-line" aria-label="{{ $signatory['label'] }} electronic signature">
                            @if ($signature)
                                @if ($signature->signature_image_path)
                                    <img src="{{ route('e-signatures.image', $signature) }}" alt="{{ $signature->signer_name ?? $signatory['label'] }} signature">
                                @else
                                    <span>{{ $signature->typed_signature_name ?: $signature->signer_name }}</span>
                                @endif
                            @endif
                        </div>
                        <div class="app-signatory-name app-signatory-field" data-signatory-key="{{ $key }}" data-signatory-column="name">{{ $text($signatoryValue($key, 'name')) }}</div>
                        <div>Signature over Printed Name</div>
                        @php $selectedTitle = $signatoryTitle($key); @endphp
                        @if ($isEditable)
                            <select class="app-signatory-title app-signatory-title-select" data-signatory-key="{{ $key }}" data-signatory-column="title" data-manually-selected="{{ $signatoryTitleWasManuallySelected($key) && filled($selectedTitle) ? 'true' : 'false' }}" aria-label="{{ $signatory['label'] }} position or designation" @disabled($signatoriesLocked)>
                                <option value="" data-office="" @selected($selectedTitle === '')>-- Select role --</option>
                                @foreach ($signatoryDesignationOptions as $designation => $office)
                                    <option value="{{ $designation }}" data-office="{{ $office }}" @selected($selectedTitle === $designation)>{{ $designation }}</option>
                                @endforeach
                                @if (filled($selectedTitle) && ! array_key_exists($selectedTitle, $signatoryDesignationOptions))
                                    <option value="{{ $selectedTitle }}" data-office="{{ $signatoryOffice($key) }}" selected>{{ $text($selectedTitle) }}</option>
                                @endif
                            </select>
                        @else
                            <div class="app-signatory-title app-signatory-field" data-signatory-key="{{ $key }}" data-signatory-column="title">{{ $text($selectedTitle) }}</div>
                        @endif
                        <div>Position/Designation</div>
                        <div class="app-signatory-office app-signatory-field" data-signatory-key="{{ $key }}" data-signatory-column="office">{{ $text($signatoryOffice($key)) }}</div>
                        <div class="app-signatory-date">Date: <span>{{ $signatureDate($signature) }}</span></div>
                    </div>
                @endforeach
            </section>
        </div>
    </section>
</div>

<div id="appPrintArea" class="app-print-area">
    <section id="appPrintPaper" class="app-landscape-page"></section>
</div>

@if ($isEditable)
    <style>
        .app-item-row--locked .app-cell {
            background: #f8fafc;
            color: #475569;
            cursor: not-allowed;
        }

        .app-item-row--locked .app-cell:focus {
            outline: none;
        }

        @media print {
            .app-item-row--locked .app-cell {
                background: #fff;
                color: #000;
            }
        }
    </style>
@endif

<script>
    (function () {
        window.PaperTrailRowTools = window.PaperTrailRowTools || (() => {
            function normalizeCellText(value) {
                return (value || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
            }

            function getCellValue(cell) {
                if (!cell) return '';
                if (cell.matches('input, textarea, select')) return normalizeCellText(cell.value);
                return normalizeCellText(cell.innerText || cell.textContent || '');
            }

            function isRowEmpty(row, fieldSelector = '[contenteditable="true"], .app-cell') {
                if (!row) return true;
                const fields = Array.from(row.querySelectorAll(fieldSelector)).filter((field) => !field.matches('input[type="hidden"], [readonly], [disabled]'));
                if (!fields.length) return normalizeCellText(row.innerText || row.textContent || '') === '';
                return fields.every((field) => getCellValue(field) === '');
            }

            return { normalizeCellText, getCellValue, isRowEmpty };
        })();

        const editor = document.getElementById('appEditor');
        const form = document.querySelector('.app-editor-form');
        const isEditable = @json($isEditable);
        const signatoriesLocked = @json($signatoriesLocked);
        const columns = [
            'project_title',
            'end_user_unit',
            'general_description',
            'mode_of_procurement',
            'early_procurement_activity',
            'bid_evaluation_criteria',
            'start_procurement_activity',
            'end_procurement_activity',
            'source_of_funds',
            'estimated_budget',
            'procurement_strategy_or_tools',
            'remarks'
        ];
        const categories = @json(array_keys($categories));
        const minimumRows = @json($minimumRows);
        const lockedPpmpIds = new Set(@json($lockedPpmpIds->all()).map((id) => String(id)));

        if (!editor) return;

        function isLockedRow(row) {
            if (!row) return false;
            const ppmpId = row.dataset.sourcePpmpDocumentId || '';

            return row.dataset.appLockedRow === 'true' || (ppmpId !== '' && lockedPpmpIds.has(String(ppmpId)));
        }

        function setRowLockedState(row) {
            if (!row || !isLockedRow(row)) return;
            row.dataset.appLockedRow = 'true';
            row.classList.add('app-item-row--locked');
            row.setAttribute('aria-readonly', 'true');
            row.querySelectorAll('.app-cell').forEach((cell) => {
                cell.removeAttribute('contenteditable');
                cell.removeAttribute('spellcheck');
                cell.setAttribute('aria-readonly', 'true');
                cell.setAttribute('tabindex', '-1');
            });
        }

        function editableCells() {
            return Array.from(editor.querySelectorAll('.app-cell[contenteditable="true"], .app-editable-field[contenteditable="true"], .app-signatory-field[contenteditable="true"]'))
                .filter((cell) => !isLockedRow(cell.closest('.app-item-row')));
        }

        function focusCell(cell) {
            if (!cell) return;
            cell.focus();
            const range = document.createRange();
            range.selectNodeContents(cell);
            range.collapse(false);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }

        function number(value) {
            const parsed = Number(String(value || '').replace(/[^0-9.-]/g, ''));
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function format(value) {
            return value > 0 ? value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function setEditableState() {
            if (!isEditable) return;
            editor.querySelectorAll('.app-cell, .app-editable-field, .app-signatory-field').forEach((cell) => {
                if (signatoriesLocked && cell.classList.contains('app-signatory-field')) {
                    cell.removeAttribute('contenteditable');
                    cell.removeAttribute('spellcheck');
                    cell.setAttribute('aria-readonly', 'true');
                    cell.setAttribute('tabindex', '-1');
                    return;
                }

                if (isLockedRow(cell.closest('.app-item-row'))) {
                    cell.removeAttribute('contenteditable');
                    cell.removeAttribute('spellcheck');
                    cell.setAttribute('aria-readonly', 'true');
                    cell.setAttribute('tabindex', '-1');
                    return;
                }

                cell.setAttribute('contenteditable', 'true');
                cell.setAttribute('spellcheck', 'false');
                cell.removeAttribute('aria-readonly');
                cell.removeAttribute('tabindex');
            });
            editor.querySelectorAll('.app-item-row').forEach(setRowLockedState);
            recalcAll();
        }

        function cellHtml(column) {
            const classes = ['app-cell'];
            if (['project_title', 'remarks'].includes(column)) classes.push('app-left');
            if (['estimated_budget'].includes(column)) classes.push('app-right', 'app-money');
            if (!classes.includes('app-left') && !classes.includes('app-right')) classes.push('app-center');
            if (column === 'project_title') classes.push('app-project-cell');
            if (column === 'remarks') classes.push('app-remarks-cell');

            return `<td class="${classes.join(' ')}" contenteditable="true" spellcheck="false" data-column="${column}"></td>`;
        }

        function rowHtml(category = 'general_requirements') {
            return `<tr class="app-item-row" data-category="${escapeHtml(category)}">${columns.map((column) => cellHtml(column)).join('')}</tr>`;
        }

        function insertRowForCategory(category = 'general_requirements') {
            const rows = Array.from(editor.querySelectorAll(`.app-item-row[data-category="${category}"]`));
            if (rows.length) {
                rows[rows.length - 1].insertAdjacentHTML('afterend', rowHtml(category));
                return rows[rows.length - 1].nextElementSibling;
            }

            const section = editor.querySelector(`[data-category-section="${category}"]`);
            section?.insertAdjacentHTML('afterend', rowHtml(category));
            return section?.nextElementSibling || null;
        }

        function normalizeCategory(category) {
            return categories.includes(category) ? category : 'general_requirements';
        }

        function firstEmptyRowForCategory(category) {
            return Array.from(editor.querySelectorAll(`.app-item-row[data-category="${category}"]`))
                .filter((row) => !isLockedRow(row))
                .find((row) => window.PaperTrailRowTools.isRowEmpty(row, '.app-cell'));
        }

        function formatImportAmount(value) {
            const parsed = number(value);
            return parsed > 0 ? format(parsed) : '';
        }

        function setCellText(row, column, value) {
            const cell = row.querySelector(`[data-column="${column}"]`);
            if (!cell) return;
            cell.innerText = column === 'estimated_budget' ? formatImportAmount(value) : String(value || '');
        }

        function fillAppRow(row, item) {
            if (!row) return;

            if (item.source_ppmp_document_id) {
                row.dataset.sourcePpmpDocumentId = String(item.source_ppmp_document_id);
            } else {
                delete row.dataset.sourcePpmpDocumentId;
            }

            if (item.source_ppmp_item_id) {
                row.dataset.sourcePpmpItemId = String(item.source_ppmp_item_id);
            } else {
                delete row.dataset.sourcePpmpItemId;
            }

            setRowLockedState(row);
            columns.forEach((column) => setCellText(row, column, item[column] || ''));
        }

        function addItemToWorksheet(item) {
            const category = normalizeCategory(item.category || 'general_requirements');
            const row = firstEmptyRowForCategory(category) || insertRowForCategory(category);
            fillAppRow(row, { ...item, category });
        }

        function worksheetHasPpmp(ppmpId) {
            return Array.from(editor.querySelectorAll('.app-item-row'))
                .some((row) => String(row.dataset.sourcePpmpDocumentId || '') === String(ppmpId));
        }

        function worksheetRowsForPpmp(ppmpId) {
            return Array.from(editor.querySelectorAll('.app-item-row'))
                .filter((row) => String(row.dataset.sourcePpmpDocumentId || '') === String(ppmpId));
        }

        function ensureMinimumRowsForCategory(category) {
            const normalizedCategory = normalizeCategory(category);
            const minimum = Number(minimumRows[normalizedCategory] || 1);

            while (editor.querySelectorAll(`.app-item-row[data-category="${normalizedCategory}"]`).length < minimum) {
                insertRowForCategory(normalizedCategory);
            }
        }

        function removePpmpFromWorksheet(ppmpId) {
            if (lockedPpmpIds.has(String(ppmpId))) {
                window.PaperTrailDialog?.notice('This PPMP is already locked in a submitted APP version and cannot be removed.', {
                    title: 'PPMP Locked',
                });

                return 0;
            }

            const rows = worksheetRowsForPpmp(ppmpId);
            const touchedCategories = new Set(rows.map((row) => normalizeCategory(row.dataset.category)));

            rows.forEach((row) => row.remove());
            touchedCategories.forEach(ensureMinimumRowsForCategory);

            recalcAll();
            updateCreateImportButtons();

            return rows.length;
        }

        function importItemsForButton(button) {
            const data = document.querySelector(`[data-app-ppmp-items="${button.dataset.ppmpId}"]`);
            if (!data) return [];

            try {
                const parsed = JSON.parse(data.textContent || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        }

        function updateCreateImportButtons() {
            document.querySelectorAll('[data-app-add-ppmp]').forEach((button) => {
                if (!button.dataset.ppmpDefaultLabel) {
                    button.dataset.ppmpDefaultLabel = button.dataset.addLabel || button.textContent.trim() || 'Add to APP';
                }

                if (!button.dataset.ppmpInitialDisabled) {
                    button.dataset.ppmpInitialDisabled = button.disabled ? '1' : '0';
                }

                const alreadyAdded = worksheetHasPpmp(button.dataset.ppmpId);
                const isLockedPpmp = lockedPpmpIds.has(String(button.dataset.ppmpId));

                if (alreadyAdded && isLockedPpmp) {
                    button.disabled = true;
                    button.textContent = 'Locked';
                    button.dataset.ppmpAdded = '1';
                    button.classList.remove('is-remove');
                    button.setAttribute('aria-label', `${button.dataset.ppmpLabel || 'PPMP'} is locked in a submitted APP version`);
                } else if (alreadyAdded) {
                    button.disabled = false;
                    button.textContent = 'Remove';
                    button.dataset.ppmpAdded = '1';
                    button.classList.add('is-remove');
                    button.setAttribute('aria-label', `Remove ${button.dataset.ppmpLabel || 'PPMP'} from APP worksheet`);
                } else {
                    button.disabled = button.dataset.ppmpInitialDisabled === '1';
                    button.textContent = button.dataset.ppmpDefaultLabel;
                    button.dataset.ppmpAdded = '0';
                    button.classList.remove('is-remove');
                    button.setAttribute('aria-label', button.dataset.ppmpDefaultLabel);
                }
            });
        }

        function showImportFeedback(message) {
            const feedback = document.querySelector('[data-app-import-feedback]');
            if (!feedback) return;
            feedback.textContent = message;
            feedback.hidden = false;
        }

        function revealCreateWorksheet() {
            const createFlow = form?.closest('[data-document-create-flow]');

            if (!createFlow || createFlow.classList.contains('is-document-form-ready')) {
                return Promise.resolve(true);
            }

            const proceedButton = createFlow.querySelector('[data-document-create-proceed]');
            if (!proceedButton) {
                return Promise.resolve(false);
            }

            proceedButton.click();

            return new Promise((resolve) => {
                window.setTimeout(() => {
                    resolve(createFlow.classList.contains('is-document-form-ready'));
                }, 420);
            });
        }

        window.addAppRow = function (category = 'general_requirements') {
            insertRowForCategory(category);
            const rows = Array.from(editor.querySelectorAll(`.app-item-row[data-category="${category}"]`));
            focusCell(rows[rows.length - 1]?.querySelector('.app-cell'));
        };

        window.addMergedAppRow = function () {
            window.addAppRow('general_requirements');
        };

        window.removeEmptyAppRows = function () {
            let removed = 0;
            categories.forEach((category) => {
                const rows = Array.from(editor.querySelectorAll(`.app-item-row[data-category="${category}"]`));
                rows.forEach((row) => {
                    const currentRows = editor.querySelectorAll(`.app-item-row[data-category="${category}"]`);
                    if (currentRows.length <= 1) return;
                    if (isLockedRow(row)) return;
                    if (window.PaperTrailRowTools.isRowEmpty(row, '.app-cell')) {
                        row.remove();
                        removed++;
                    }
                });
            });

            if (removed === 0) {
                window.PaperTrailDialog?.notice('No empty APP rows to remove.', {
                    title: 'No Empty Rows',
                });
            }
            recalcAll();
            updateCreateImportButtons();
        };

        function recalcAll() {
            let grandTotal = 0;
            let epaTotal = 0;
            let cseTotal = 0;
            let hasFilledRow = false;

            editor.querySelectorAll('.app-item-row').forEach((row) => {
                if (!window.PaperTrailRowTools.isRowEmpty(row, '.app-cell')) {
                    hasFilledRow = true;
                }

                const amount = number(row.querySelector('[data-column="estimated_budget"]')?.innerText);
                const epa = window.PaperTrailRowTools.getCellValue(row.querySelector('[data-column="early_procurement_activity"]')).toLowerCase();

                grandTotal += amount;
                if (epa === 'yes') epaTotal += amount;
                if (row.dataset.category === 'cse') cseTotal += amount;
            });

            const grand = editor.querySelector('[data-app-grand-total]');
            const epa = editor.querySelector('[data-app-epa-total]');
            const cse = editor.querySelector('[data-app-cse-total]');
            if (grand) grand.innerText = hasFilledRow ? format(grandTotal) : '';
            if (epa) epa.innerText = hasFilledRow ? format(epaTotal) : '';
            if (cse) cse.innerText = hasFilledRow ? format(cseTotal) : '';
        }

        function collectItems() {
            return Array.from(editor.querySelectorAll('.app-item-row'))
                .filter((row) => !window.PaperTrailRowTools.isRowEmpty(row, '.app-cell'))
                .map((row) => {
                    const item = { category: row.dataset.category || 'general_requirements' };
                    if (row.dataset.sourcePpmpDocumentId) item.source_ppmp_document_id = row.dataset.sourcePpmpDocumentId;
                    if (row.dataset.sourcePpmpItemId) item.source_ppmp_item_id = row.dataset.sourcePpmpItemId;
                    columns.forEach((column) => {
                        item[column] = window.PaperTrailRowTools.getCellValue(row.querySelector(`[data-column="${column}"]`));
                    });
                    return item;
                });
        }

        function collectSignatories() {
            const signatories = {};
            editor.querySelectorAll('[data-signatory-key][data-signatory-column]').forEach((cell) => {
                signatories[cell.dataset.signatoryKey] = signatories[cell.dataset.signatoryKey] || {};
                signatories[cell.dataset.signatoryKey][cell.dataset.signatoryColumn] = window.PaperTrailRowTools.getCellValue(cell);

                if (cell.matches('.app-signatory-title-select')) {
                    signatories[cell.dataset.signatoryKey].title_manually_selected = cell.value !== '' && cell.dataset.manuallySelected === 'true';
                }
            });
            return signatories;
        }

        function fieldValue(fieldName) {
            const metaControl = document.querySelector(`[data-app-meta-field="${fieldName}"]`);
            if (metaControl) return metaControl.value || '';
            const hiddenField = document.querySelector(`[data-app-hidden-field="${fieldName}"]`);
            if (fieldName === 'office_id') return hiddenField?.value || '';
            const fieldCell = editor.querySelector(`[data-field="${fieldName}"]`);
            if (fieldCell) return window.PaperTrailRowTools.getCellValue(fieldCell);
            return hiddenField?.value || '';
        }

        function syncSignatoryDesignation(select) {
            const key = select?.dataset?.signatoryKey;
            const selected = select?.selectedOptions?.[0];
            const office = selected?.dataset?.office || '';
            const officeCell = key ? editor.querySelector(`[data-signatory-key="${key}"][data-signatory-column="office"]`) : null;
            if (officeCell && selected) officeCell.innerText = office;
        }

        function prepareCleanClone() {
            const clone = editor.cloneNode(true);
            clone.querySelectorAll('.app-plan-option input[type="radio"]').forEach((input) => {
                const box = input.parentElement?.querySelector('.app-plan-box');
                if (input.checked) box?.classList.add('is-checked');
                input.remove();
            });
            clone.querySelectorAll('.app-version-input').forEach((input) => {
                const span = document.createElement('span');
                span.textContent = input.value || '___';
                input.replaceWith(span);
            });
            clone.querySelectorAll('.app-signatory-title-select').forEach((select) => {
                const title = document.createElement('div');
                title.className = 'app-signatory-title app-signatory-field';
                title.dataset.signatoryKey = select.dataset.signatoryKey || '';
                title.dataset.signatoryColumn = select.dataset.signatoryColumn || 'title';
                title.textContent = select.value ? (select.selectedOptions?.[0]?.textContent?.trim() || select.value || '') : '';
                select.replaceWith(title);
            });
            clone.querySelectorAll('[contenteditable]').forEach((el) => {
                el.removeAttribute('contenteditable');
                el.removeAttribute('spellcheck');
            });
            clone.querySelectorAll('.is-focused, .focused, .active').forEach((el) => {
                el.classList.remove('is-focused', 'focused', 'active');
            });
            clone.querySelectorAll('[style]').forEach((el) => {
                if ((el.getAttribute('style') || '').includes('outline')) {
                    el.style.outline = 'none';
                }
            });
            return clone;
        }

        function appPrintCss() {
            return `
                @page { size: A4 landscape; margin: 0; }
                html, body {
                    width: 297mm;
                    min-width: 297mm;
                    min-height: 210mm;
                    margin: 0;
                    padding: 0;
                    background: #fff;
                    color: #000;
                    font-family: Arial, sans-serif;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .app-print-sheet {
                    width: 297mm;
                    min-width: 297mm;
                    min-height: 210mm;
                    padding: 6.5mm 20mm 8mm;
                    box-sizing: border-box;
                    background: #fff;
                    color: #000;
                    overflow: visible;
                }
                .app-document, .app-document * {
                    box-sizing: border-box;
                    font-family: Arial, sans-serif !important;
                    color: #000 !important;
                }
                .app-document { width: 100%; font-size: 5.8pt; line-height: 1.05; }
                .app-official-header {
                    display: grid;
                    grid-template-columns: 28mm 1fr 28mm;
                    align-items: center;
                    width: 190mm;
                    max-width: 100%;
                    min-height: 17mm;
                    margin: 0 auto 2mm;
                    text-align: center;
                }
                .app-logo-slot { display: flex; align-items: center; justify-content: center; min-height: 15mm; }
                .app-logo-slot img { max-width: 16.5mm; max-height: 16.5mm; object-fit: contain; }
                .app-bagong-slot img { max-width: 22mm; max-height: 16mm; }
                .app-header-text { font-size: 7.2pt; font-weight: 400; line-height: 1.04; }
                .app-header-text > div, .app-header-text .app-editable-field { font-size: 7.2pt !important; line-height: 1.04 !important; }
                .app-municipality { font-size: 7.2pt !important; font-weight: 700; line-height: 1.04 !important; }
                .app-official-title {
                    margin: 0 auto 0.8mm;
                    padding: 0;
                    border: 0;
                    text-align: center;
                    font-size: 10.2pt !important;
                    font-weight: 700;
                    line-height: 1.05 !important;
                }
                .app-plan-type-row {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 24mm;
                    min-height: 5.2mm;
                    margin-bottom: 0.8mm;
                    font-size: 7.2pt;
                    line-height: 1;
                }
                .app-plan-option { display: inline-flex; align-items: center; gap: 3mm; font-style: normal; }
                .app-plan-box {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 5mm;
                    height: 5mm;
                    border: 1px solid #16a34a;
                    background: #fff;
                    box-sizing: border-box;
                }
                .app-plan-box.is-checked::after {
                    content: "";
                    width: 2.5mm;
                    height: 2.5mm;
                    background: #16a34a;
                    display: block;
                }
                .app-plan-option em { font-style: normal; }
                .app-table {
                    width: 100%;
                    table-layout: fixed;
                    border-collapse: collapse;
                    font-size: 5.6pt !important;
                    line-height: 1.05 !important;
                }
                .app-table th, .app-table td {
                    border: 1px solid #000;
                    padding: 1px 2px;
                    vertical-align: middle;
                    box-sizing: border-box;
                    white-space: normal;
                    overflow-wrap: normal;
                    word-break: normal;
                    background: #fff;
                    font-size: 5.6pt !important;
                    line-height: 1.05 !important;
                    height: 4.2mm;
                    min-height: 4.2mm;
                }
                .app-table th { text-align: center; font-weight: 700; hyphens: none; }
                .app-group-row th { height: 3.2mm; font-size: 5.5pt !important; }
                .app-heading-row th { height: 15mm; font-size: 5.45pt !important; }
                .app-column-row th { background: #fff9bf; font-size: 5.7pt !important; font-weight: 400; height: 4mm; }
                .app-category-row td { font-weight: 700; text-align: left; height: 4.2mm; }
                .app-left, .app-project-cell, .app-remarks-cell { text-align: left !important; }
                .app-center { text-align: center !important; }
                .app-right, .app-money { text-align: right !important; white-space: nowrap !important; }
                .app-note-total-row { display: grid; grid-template-columns: 1.2fr 1fr; gap: 8mm; margin-top: 1.5mm; font-size: 5.8pt; }
                .app-note-total-row p { margin: 0; }
                .app-total-summary { display: grid; gap: 1.5mm; font-weight: 700; }
                .app-total-summary div { display: grid; grid-template-columns: 1fr 24mm; gap: 4mm; }
                .app-total-summary strong { text-align: right; }
                .app-signatories { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16mm; margin-top: 9mm; font-size: 5.8pt; text-align: center; }
                .app-signatory { display: grid; gap: 1.1mm; align-content: start; }
                .app-signatory-label { text-align: left; min-height: 5mm; }
                .app-signatory-authority { text-align: left; min-height: 4mm; }
                .app-electronic-signature-line { display: flex; align-items: end; justify-content: center; min-height: 8mm; }
                .app-electronic-signature-line img { max-width: 32mm; max-height: 8mm; object-fit: contain; }
                .app-electronic-signature-line span { font-family: "Brush Script MT", cursive !important; font-size: 8pt !important; }
                .app-signatory-name { margin-top: 4mm; border-bottom: 1px solid #000; font-weight: 700; }
                .app-signatory-title, .app-signatory-office { border-bottom: 1px solid #000; min-height: 3.6mm; }
                .app-signatory-date { margin-top: 4mm; text-align: left; }
                [contenteditable], button, .btn, .no-print, .app-toolbar { outline: none !important; background: transparent !important; }
                button, .btn, .no-print, .app-toolbar { display: none !important; }
            `;
        }

        function writeIsolatedPrintDocument(printWindow, appHtml) {
            printWindow.document.open();
            printWindow.document.write(`<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Annual Procurement Plan</title>
    <style>${appPrintCss()}</style>
</head>
<body>
    <section class="app-print-sheet">
        <div class="app-document">${appHtml}</div>
    </section>
    <script>
        window.onload = function () {
            setTimeout(function () {
                window.focus();
                window.print();
            }, 300);
        };

        window.onafterprint = function () {
            setTimeout(function () {
                window.close();
            }, 300);
        };
    <\/script>
</body>
</html>`);
            printWindow.document.close();
        }

        window.prepareCleanAppClone = function () {
            document.activeElement?.blur?.();
            const clone = prepareCleanClone();
            return clone.innerHTML;
        };

        window.prepareAppPrint = function () {
            document.activeElement?.blur?.();
            const printPaper = document.getElementById('appPrintPaper');
            if (!printPaper) return false;
            const clone = prepareCleanClone();
            printPaper.innerHTML = '';
            printPaper.appendChild(clone);
            return true;
        };

        window.printAppDocument = function () {
            const appHtml = window.prepareCleanAppClone();
            if (!appHtml) {
                window.PaperTrailDialog?.notice('APP document not found.', {
                    title: 'Document Not Found',
                });
                return;
            }

            const printWindow = window.open('', '_blank', 'width=1200,height=800');
            if (!printWindow) {
                window.PaperTrailDialog?.notice('Please allow pop-ups to print the APP document.', {
                    title: 'Pop-Up Blocked',
                });
                return;
            }

            writeIsolatedPrintDocument(printWindow, appHtml);
        };

        function collectAppData() {
            recalcAll();
            const clone = prepareCleanClone();
            const documentHtml = document.getElementById('app_document_html');
            const documentText = document.getElementById('app_document_text');
            const itemsJson = document.getElementById('app_items_json');
            const signatoriesJson = document.getElementById('app_signatories_json');

            if (documentHtml) documentHtml.value = clone.innerHTML.trim();
            if (documentText) documentText.value = editor.innerText.trim();
            if (itemsJson) itemsJson.value = JSON.stringify(collectItems());
            if (signatoriesJson) signatoriesJson.value = JSON.stringify(collectSignatories());
            form?.querySelectorAll('[data-app-hidden-field]').forEach((input) => {
                input.value = fieldValue(input.dataset.appHiddenField);
            });
        }

        document.addEventListener('keydown', function (event) {
            const cell = event.target.closest('.app-cell, .app-editable-field, .app-signatory-field');
            if (!cell || !editor.contains(cell) || !isEditable) return;
            if (isLockedRow(cell.closest('.app-item-row'))) return;
            if (event.key === 'Tab') {
                event.preventDefault();
                const all = editableCells();
                focusCell(all[all.indexOf(cell) + (event.shiftKey ? -1 : 1)] || cell);
            }
            if (event.key === 'Enter' && cell.classList.contains('app-cell')) {
                event.preventDefault();
                const row = cell.closest('.app-item-row');
                focusCell(row?.nextElementSibling?.querySelector(`[data-column="${cell.dataset.column}"]`) || cell);
            }
        });

        document.addEventListener('paste', function (event) {
            const cell = event.target.closest('.app-cell');
            if (!cell || !editor.contains(cell) || !isEditable) return;
            if (isLockedRow(cell.closest('.app-item-row'))) return;
            const text = event.clipboardData?.getData('text/plain') || '';
            if (!text.includes('\t') && !text.includes('\n')) return;
            event.preventDefault();
            const startRow = Array.from(editor.querySelectorAll('.app-item-row')).indexOf(cell.closest('.app-item-row'));
            const startColumn = columns.indexOf(cell.dataset.column);
            text.replace(/\r/g, '').split('\n').filter((line) => line.length).forEach((line, rowOffset) => {
                while (editor.querySelectorAll('.app-item-row').length <= startRow + rowOffset) window.addAppRow();
                const row = editor.querySelectorAll('.app-item-row')[startRow + rowOffset];
                if (isLockedRow(row)) return;
                line.split('\t').forEach((value, colOffset) => {
                    const target = row.querySelector(`[data-column="${columns[startColumn + colOffset]}"]`);
                    if (target) target.innerText = value.trim();
                });
            });
            recalcAll();
        });

        document.addEventListener('input', function (event) {
            if (!event.target.closest('.app-cell, .app-editable-field, .app-signatory-field') || !editor.contains(event.target)) return;
            if (isLockedRow(event.target.closest('.app-item-row'))) return;
            recalcAll();
        });

        document.querySelectorAll('.app-signatory-title-select').forEach((select) => {
            select.addEventListener('change', function () {
                select.dataset.manuallySelected = select.value ? 'true' : 'false';
                syncSignatoryDesignation(select);
                recalcAll();
            });
        });

        document.addEventListener('click', async function (event) {
            const button = event.target.closest('[data-app-add-ppmp]');
            if (!button) return;

            const ppmpId = button.dataset.ppmpId;
            if (worksheetHasPpmp(ppmpId)) {
                if (lockedPpmpIds.has(String(ppmpId))) {
                    window.PaperTrailDialog?.notice('This PPMP is already locked in a submitted APP version and cannot be removed.', {
                        title: 'PPMP Locked',
                    });
                    updateCreateImportButtons();
                    return;
                }

                const label = button.dataset.ppmpLabel || 'this PPMP';
                const confirmed = window.PaperTrailDialog?.confirm
                    ? await window.PaperTrailDialog.confirm(
                        `Remove ${label} from this APP worksheet? The PPMP will remain accepted and can be added again.`,
                        {
                            title: 'Remove PPMP from APP?',
                            confirmLabel: 'Remove from APP',
                            type: 'danger',
                        },
                    )
                    : true;

                if (confirmed) {
                    const removed = removePpmpFromWorksheet(ppmpId);
                    showImportFeedback(`${removed} APP row${removed === 1 ? '' : 's'} removed from ${label}.`);
                }

                updateCreateImportButtons();
                return;
            }

            const items = importItemsForButton(button);
            if (!items.length) {
                window.PaperTrailDialog?.notice('This PPMP has no item rows available for APP consolidation.', {
                    title: 'No PPMP Items',
                });
                return;
            }

            items.forEach(addItemToWorksheet);
            recalcAll();
            updateCreateImportButtons();
            showImportFeedback(`${items.length} APP row${items.length === 1 ? '' : 's'} added from ${button.dataset.ppmpLabel || 'the selected PPMP'}.`);
            await revealCreateWorksheet();
        });

        form?.addEventListener('submit', collectAppData);
        document.querySelectorAll('[data-app-meta-field]').forEach((input) => {
            input.addEventListener('input', function () {
                const target = editor.querySelector(`[data-field="${input.dataset.appMetaField}"]`);
                if (target) target.innerText = input.value;
            });
        });
        window.addEventListener('beforeprint', function () {
            window.prepareAppPrint();
            document.body.classList.add('app-is-printing');
        });
        window.addEventListener('afterprint', function () {
            document.body.classList.remove('app-is-printing');
        });
        setEditableState();
        recalcAll();
        updateCreateImportButtons();
    })();
</script>
