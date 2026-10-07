@php
    $mode = $mode ?? 'show';
    $isEditable = in_array($mode, ['create', 'edit'], true);
    $field = fn (string $name, mixed $default = '') => old($name, filled($abstract->{$name} ?? null) ? $abstract->{$name} : $default);
    $money = fn ($value) => filled($value) ? number_format((float) $value, 2, '.', '') : '';
    $text = fn ($value, string $fallback = '') => e(filled($value) ? $value : $fallback);
    $abstractDate = old('abstract_date', $abstract->abstract_date?->format('Y-m-d') ?? now()->toDateString());
    $items = collect(old('items_json') ? json_decode(old('items_json'), true) : null);
    $suppliers = collect(old('suppliers_json') ? json_decode(old('suppliers_json'), true) : ($abstract->suppliers_json ?? []));
    $awards = collect(old('awards_json') ? json_decode(old('awards_json'), true) : ($abstract->awards_json ?? []));
    $committee = collect(old('committee_json') ? json_decode(old('committee_json'), true) : ($abstract->committee_json ?? []));
    $committeeDefaults = collect([
        'bac_chairperson' => 'ROEL J. BANO',
        'bac_vice_chairperson' => 'EDMAR T. TAMBIS',
        'bac_member_1' => 'PERPETUA D. SALAN',
        'bac_member_2' => 'ROGELIO A. LAYO',
        'bac_member_alternate' => 'JESSA G. RESUS',
    ]);
    $committeeRoleDefinitions = collect([
        'bac_chairperson' => ['label' => 'BAC Chairperson'],
        'bac_vice_chairperson' => ['label' => 'BAC Vice Chairperson'],
        'bac_member_1' => ['label' => 'BAC Member I'],
        'bac_member_2' => ['label' => 'BAC Member II'],
        'bac_member_alternate' => ['label' => 'BAC Member III'],
    ]);
    $committeeEntry = function (string $role) use ($committee): array {
        $entry = $committee->get($role);

        if (is_array($entry)) {
            return $entry;
        }

        return [
            'name' => (string) ($entry ?? ''),
            'designation' => (string) ($committee->get($role.'_designation') ?? ''),
        ];
    };
    $committeeName = function (string $role) use ($committeeEntry, $committeeDefaults): string {
        $entry = $committeeEntry($role);

        return filled($entry['name'] ?? null) ? (string) $entry['name'] : (string) $committeeDefaults->get($role, '');
    };
    $committeeDesignation = function (string $role) use ($committeeEntry, $committeeRoleDefinitions): string {
        $entry = $committeeEntry($role);
        $default = (string) data_get($committeeRoleDefinitions->get($role), 'label', '');
        $designation = filled($entry['designation'] ?? null) ? (string) $entry['designation'] : $default;

        if (in_array($role, ['bac_member_1', 'bac_member_2', 'bac_member_alternate'], true)
            && in_array(strtolower(str_replace(['-', '  '], [' ', ' '], $designation)), ['bac member', 'bac member alternate'], true)) {
            return $default;
        }

        return $designation;
    };
    $committeeBlockHtml = '<tr><td colspan="10" class="abstract-cell abstract-no-border abstract-bold abstract-approval-heading" data-field="approval_heading">APPROVED BY COMMITTEE ON BIDS AND AWARDS:</td></tr><tr><td colspan="10" class="abstract-no-border"><div class="abstract-committee-grid">';

    foreach ($committeeRoleDefinitions as $role => $definition) {
        $committeeBlockHtml .= '<div class="abstract-committee-person">'
            .'<span class="abstract-cell abstract-committee-name abstract-bottom-line" data-committee-role="'.e($role).'">'.e($committeeName($role)).'</span>'
            .'<span class="abstract-cell abstract-committee-designation" data-committee-designation="'.e($role).'">'.e($committeeDesignation($role)).'</span>'
            .'</div>';
    }

    $committeeBlockHtml .= '</div></td></tr>';

    if ($items->isEmpty()) {
        if (is_array($abstract->items_json)) {
            $items = collect($abstract->items_json);
        } elseif ($abstract->relationLoaded('items') || $abstract->exists) {
            $items = $abstract->items->map(fn ($item) => [
                'item_no' => $item->item_no,
                'name_of_goods_services' => $item->name_of_goods_services,
                'quantity' => $item->quantity,
                'unit_of_measure' => $item->unit_of_measure,
                'supplier_1_amount' => $item->supplier_1_amount,
                'supplier_2_amount' => $item->supplier_2_amount,
                'supplier_3_amount' => $item->supplier_3_amount,
                'supplier_4_amount' => $item->supplier_4_amount,
                'supplier_5_amount' => $item->supplier_5_amount,
                'total_lowest_price' => $item->total_lowest_price,
            ]);
        }
    }

    $rowCount = max(22, $items->count());
    $officialHeaderRow = trim(view('abstracts.partials.official-header-row', ['colspan' => 10])->render());
    $upgradeOfficialHeader = function (?string $html) use ($officialHeaderRow) {
        if (! filled($html) || str_contains($html, 'abstract-official-header-row')) {
            return $html;
        }

        $updated = preg_replace(
            '/<tr>\s*<td\b[^>]*>\s*Republic of the Philippines\s*<\/td>\s*<\/tr>\s*<tr>\s*<td\b[^>]*>\s*Province of Southern Leyte\s*<\/td>\s*<\/tr>\s*<tr>\s*<td\b[^>]*>\s*MUNICIPALITY OF TOMAS OPPUS\s*<\/td>\s*<\/tr>\s*<tr>\s*<td\b[^>]*>\s*(?:&nbsp;|\s)*<\/td>\s*<\/tr>/i',
            $officialHeaderRow,
            $html,
            1
        );

        return $updated ?? $html;
    };
    $upgradeCommitteeBlock = function (?string $html) use ($committeeBlockHtml): ?string {
        if (! filled($html) || str_contains($html, 'abstract-committee-grid')) {
            return $html;
        }

        $pattern = '/<tr>\s*<td\b[^>]*>\s*APPROVED BY COMMITTEE ON BIDS AND AWARDS:\s*<\/td>\s*<\/tr>\s*(?:<tr>\s*<td\b[^>]*>\s*(?:&nbsp;|\s)*<\/td>\s*<\/tr>\s*)?(?:<tr\b[^>]*class=(["\'])[^"\']*abstract-signature-row[^"\']*\1[\s\S]*?<\/tr>\s*){1,3}/i';
        $updated = preg_replace($pattern, $committeeBlockHtml, $html, 1);

        if ($updated === $html) {
            $updated = preg_replace(
                '/<tr>\s*<td\b[^>]*>\s*APPROVED BY COMMITTEE ON BIDS AND AWARDS:\s*<\/td>\s*<\/tr>[\s\S]*?BAC Member\s*-?\s*Alternate[\s\S]*?<\/tr>/i',
                $committeeBlockHtml,
                $html,
                1
            );
        }

        return $updated ?? $html;
    };
    $applyCommitteeDefaults = function (?string $html) use ($committeeDefaults, $committeeRoleDefinitions): ?string {
        if (! filled($html)) {
            return $html;
        }

        foreach ($committeeDefaults as $role => $name) {
            $html = preg_replace_callback(
                '/(<[^>]+data-committee-role=(["\'])'.preg_quote($role, '/').'\2[^>]*>)([\s\S]*?)(<\/[^>]+>)/i',
                function (array $matches) use ($name): string {
                    $current = trim(html_entity_decode(strip_tags($matches[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                    return $current !== ''
                        ? $matches[0]
                        : $matches[1].e($name).$matches[4];
                },
                $html
            );
        }

        foreach ($committeeRoleDefinitions as $role => $definition) {
            $html = preg_replace_callback(
                '/(<[^>]+data-committee-designation=(["\'])'.preg_quote($role, '/').'\2[^>]*>)([\s\S]*?)(<\/[^>]+>)/i',
                function (array $matches) use ($definition): string {
                    $current = trim(html_entity_decode(strip_tags($matches[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                    return $current !== ''
                        ? $matches[0]
                        : $matches[1].e($definition['label']).$matches[4];
                },
                $html
            );
        }

        return $html;
    };
    $savedHtml = $applyCommitteeDefaults($upgradeCommitteeBlock($upgradeOfficialHeader(old('document_html', $abstract->document_html))));
    $isStandaloneSavedDocument = filled($savedHtml) && str_contains($savedHtml, 'aob-as-read-document');
    $supplierTotal = fn (int $number) => $suppliers->get("supplier_{$number}_total", '');
@endphp

@if ($isEditable)
    <input type="hidden" name="source_pr_document_id" value="{{ old('source_pr_document_id', $abstract->source_pr_document_id ?? $sourceDocument?->id) }}">
    <input type="hidden" name="source_rfq_id" value="{{ old('source_rfq_id', $abstract->source_rfq_id ?? $sourceRfq?->id) }}">
    <input type="hidden" name="source_bac_resolution_id" value="{{ old('source_bac_resolution_id', $abstract->source_bac_resolution_id ?? $sourceResolution?->id) }}">
    <input type="hidden" name="document_html" id="abstract_document_html">
    <input type="hidden" name="document_text" id="abstract_document_text">
    <input type="hidden" name="items_json" id="abstract_items_json">
    <input type="hidden" name="suppliers_json" id="abstract_suppliers_json">
    <input type="hidden" name="awards_json" id="abstract_awards_json">
    <input type="hidden" name="committee_json" id="abstract_committee_json">
    @foreach ([
        'abstract_number', 'abstract_date', 'project_name', 'implementing_office', 'abc_amount', 'purpose',
        'supplier_1_name', 'supplier_2_name', 'supplier_3_name', 'supplier_4_name', 'supplier_5_name',
        'lowest_supplier_name', 'lowest_total_amount'
    ] as $hiddenField)
        <input type="hidden" name="{{ $hiddenField }}" data-hidden-field="{{ $hiddenField }}">
    @endforeach
@endif

@if ($isStandaloneSavedDocument)
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
        }
    </style>
@endif

<div class="abstract-document-wrap">
    @if ($isStandaloneSavedDocument)
        {!! $savedHtml !!}
    @else
    <section class="abstract-a4-page abstract-document">
        @if (filled($savedHtml))
            {!! $savedHtml !!}
        @else
            <table id="abstractEditor" class="abstract-excel-form">
                <colgroup>
                    <col style="width:6%">
                    <col style="width:28%">
                    <col style="width:7%">
                    <col style="width:8%">
                    <col style="width:8%">
                    <col style="width:8%">
                    <col style="width:8%">
                    <col style="width:8%">
                    <col style="width:8%">
                    <col style="width:11%">
                </colgroup>
                <tbody>
                    @include('abstracts.partials.official-header-row', ['colspan' => 10])
                    <tr><td colspan="10" class="abstract-no-border abstract-title">ABSTRACT OF QUOTATIONS/CANVASS</td></tr>
                    <tr>
                        <td colspan="7" class="abstract-no-border"></td>
                        <td colspan="1" class="abstract-no-border abstract-right">No.</td>
                        <td colspan="2" class="abstract-cell abstract-no-border abstract-bottom-line" data-field="abstract_number">{!! $text($field('abstract_number')) !!}</td>
                    </tr>
                    <tr>
                        <td colspan="7" class="abstract-no-border"></td>
                        <td colspan="1" class="abstract-no-border abstract-right">Date</td>
                        <td colspan="2" class="abstract-cell abstract-no-border abstract-bottom-line" data-field="abstract_date">{!! $text($abstractDate) !!}</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="abstract-no-border">Name of the Project:</td>
                        <td colspan="8" class="abstract-cell abstract-no-border abstract-bottom-line" data-field="project_name">{!! $text($field('project_name')) !!}</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="abstract-no-border">Implementing Office:</td>
                        <td colspan="8" class="abstract-cell abstract-no-border abstract-bottom-line" data-field="implementing_office">{!! $text($field('implementing_office')) !!}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="abstract-no-border">Approved Budget for the Contract:</td>
                        <td colspan="3" class="abstract-cell abstract-no-border abstract-bottom-line abstract-right" data-field="abc_amount">{!! $text($money($field('abc_amount'))) !!}</td>
                        <td colspan="4" class="abstract-no-border"></td>
                    </tr>
                    <tr><td colspan="10" class="abstract-no-border">&nbsp;</td></tr>
                    <tr class="abstract-table-header">
                        <th>ITEM NO.</th>
                        <th>NAME OF GOODS/SERVICES</th>
                        <th>QUANTITY</th>
                        <th>UNIT OF MEASURE</th>
                        <th class="abstract-cell" data-field="supplier_1_name">1<br>AMOUNT / UNIT<br>{!! $text($field('supplier_1_name')) !!}</th>
                        <th class="abstract-cell" data-field="supplier_2_name">2<br>AMOUNT / UNIT<br>{!! $text($field('supplier_2_name')) !!}</th>
                        <th class="abstract-cell" data-field="supplier_3_name">3<br>AMOUNT / UNIT<br>{!! $text($field('supplier_3_name')) !!}</th>
                        <th class="abstract-cell" data-field="supplier_4_name">4<br>AMOUNT / UNIT<br>{!! $text($field('supplier_4_name')) !!}</th>
                        <th class="abstract-cell" data-field="supplier_5_name">5<br>AMOUNT / UNIT<br>{!! $text($field('supplier_5_name')) !!}</th>
                        <th>TOTAL LOWEST PRICE</th>
                    </tr>
                    @for ($index = 0; $index < $rowCount; $index++)
                        @php $item = $items->get($index, []); @endphp
                        <tr class="abstract-item-row" data-row="{{ $index }}">
                            <td class="abstract-cell abstract-item-cell abstract-center" data-column="item_no" data-row="{{ $index }}">{!! $text($item['item_no'] ?? '') !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-description-cell" data-column="name_of_goods_services" data-row="{{ $index }}">{!! $text($item['name_of_goods_services'] ?? '') !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-right" data-column="quantity" data-row="{{ $index }}">{!! $text($money($item['quantity'] ?? null)) !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-center" data-column="unit_of_measure" data-row="{{ $index }}">{!! $text($item['unit_of_measure'] ?? '') !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" data-column="supplier_1_amount" data-row="{{ $index }}">{!! $text($money($item['supplier_1_amount'] ?? null)) !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" data-column="supplier_2_amount" data-row="{{ $index }}">{!! $text($money($item['supplier_2_amount'] ?? null)) !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" data-column="supplier_3_amount" data-row="{{ $index }}">{!! $text($money($item['supplier_3_amount'] ?? null)) !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" data-column="supplier_4_amount" data-row="{{ $index }}">{!! $text($money($item['supplier_4_amount'] ?? null)) !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" data-column="supplier_5_amount" data-row="{{ $index }}">{!! $text($money($item['supplier_5_amount'] ?? null)) !!}</td>
                            <td class="abstract-cell abstract-item-cell abstract-right lowest-price-cell" data-column="total_lowest_price" data-row="{{ $index }}">{!! $text($money($item['total_lowest_price'] ?? null)) !!}</td>
                        </tr>
                    @endfor
                    <tr class="abstract-total-row">
                        <td colspan="4" class="abstract-right abstract-bold">TOTAL</td>
                        <td class="abstract-cell abstract-right abstract-bold" data-field="supplier_1_total">{!! $text($money($supplierTotal(1))) !!}</td>
                        <td class="abstract-cell abstract-right abstract-bold" data-field="supplier_2_total">{!! $text($money($supplierTotal(2))) !!}</td>
                        <td class="abstract-cell abstract-right abstract-bold" data-field="supplier_3_total">{!! $text($money($supplierTotal(3))) !!}</td>
                        <td class="abstract-cell abstract-right abstract-bold" data-field="supplier_4_total">{!! $text($money($supplierTotal(4))) !!}</td>
                        <td class="abstract-cell abstract-right abstract-bold" data-field="supplier_5_total">{!! $text($money($supplierTotal(5))) !!}</td>
                        <td class="abstract-cell abstract-right abstract-bold" data-field="lowest_total_amount">{!! $text($money($field('lowest_total_amount'))) !!}</td>
                    </tr>
                    <tr><td colspan="10" class="abstract-no-border">&nbsp;</td></tr>
                    <tr class="abstract-note-row"><td colspan="10" class="abstract-cell abstract-no-border abstract-bold" data-field="note_title">Note:</td></tr>
                    @for ($index = 1; $index <= 3; $index++)
                        <tr class="abstract-note-row">
                            <td class="abstract-no-border abstract-center">{{ $index }}</td>
                            <td colspan="9" class="abstract-cell abstract-no-border abstract-bottom-line" data-award-row="{{ $index }}">Items No. ____ awarded to ____ being quoted the lowest price.</td>
                        </tr>
                    @endfor
                    <tr><td colspan="10" class="abstract-no-border">&nbsp;</td></tr>
                    <tr>
                        <td colspan="10" class="abstract-cell abstract-no-border" data-field="certification_text">Certification: We hereby certify that the above procurement is in accordance with the approved revised procurement plan for 202__.</td>
                    </tr>
                    <tr><td colspan="10" class="abstract-no-border">&nbsp;</td></tr>
                    <tr><td colspan="10" class="abstract-cell abstract-no-border abstract-bold abstract-approval-heading" data-field="approval_heading">APPROVED BY COMMITTEE ON BIDS AND AWARDS:</td></tr>
                    <tr>
                        <td colspan="10" class="abstract-no-border">
                            <div class="abstract-committee-grid">
                                <div class="abstract-committee-person">
                                    <span class="abstract-cell abstract-committee-name abstract-bottom-line" data-committee-role="bac_chairperson">{{ $committeeName('bac_chairperson') }}</span>
                                    <span class="abstract-cell abstract-committee-designation" data-committee-designation="bac_chairperson">{{ $committeeDesignation('bac_chairperson') }}</span>
                                </div>
                                <div class="abstract-committee-person">
                                    <span class="abstract-cell abstract-committee-name abstract-bottom-line" data-committee-role="bac_vice_chairperson">{{ $committeeName('bac_vice_chairperson') }}</span>
                                    <span class="abstract-cell abstract-committee-designation" data-committee-designation="bac_vice_chairperson">{{ $committeeDesignation('bac_vice_chairperson') }}</span>
                                </div>
                                <div class="abstract-committee-person">
                                    <span class="abstract-cell abstract-committee-name abstract-bottom-line" data-committee-role="bac_member_1">{{ $committeeName('bac_member_1') }}</span>
                                    <span class="abstract-cell abstract-committee-designation" data-committee-designation="bac_member_1">{{ $committeeDesignation('bac_member_1') }}</span>
                                </div>
                                <div class="abstract-committee-person">
                                    <span class="abstract-cell abstract-committee-name abstract-bottom-line" data-committee-role="bac_member_2">{{ $committeeName('bac_member_2') }}</span>
                                    <span class="abstract-cell abstract-committee-designation" data-committee-designation="bac_member_2">{{ $committeeDesignation('bac_member_2') }}</span>
                                </div>
                                <div class="abstract-committee-person">
                                    <span class="abstract-cell abstract-committee-name abstract-bottom-line" data-committee-role="bac_member_alternate">{{ $committeeName('bac_member_alternate') }}</span>
                                    <span class="abstract-cell abstract-committee-designation" data-committee-designation="bac_member_alternate">{{ $committeeDesignation('bac_member_alternate') }}</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        @endif
    </section>
    @endif
</div>

<div id="abstractPrintArea" class="abstract-print-area">
    <section id="abstractPrintPaper" class="abstract-a4-page abstract-document"></section>
</div>

@if ($isEditable)
    <script>
        (function () {
            const editor = document.getElementById('abstractEditor');
            const form = document.querySelector('.abstract-editor-form');
            if (!editor) return;

            const columns = [
                'item_no', 'name_of_goods_services', 'quantity', 'unit_of_measure',
                'supplier_1_amount', 'supplier_2_amount', 'supplier_3_amount',
                'supplier_4_amount', 'supplier_5_amount', 'total_lowest_price'
            ];
            const supplierColumns = ['supplier_1_amount', 'supplier_2_amount', 'supplier_3_amount', 'supplier_4_amount', 'supplier_5_amount'];

            function setEditableState() {
                editor.querySelectorAll('.abstract-cell').forEach((cell) => cell.setAttribute('contenteditable', 'true'));
                reindexRows();
                recalcAll();
            }

            function cells() {
                return Array.from(editor.querySelectorAll('.abstract-cell[contenteditable="true"]'));
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

            function moveToNextCell(cell, offset) {
                const all = cells();
                focusCell(all[all.indexOf(cell) + offset] || cell);
            }

            function moveBelow(cell) {
                const row = cell.closest('.abstract-item-row');
                const column = cell.dataset.column;
                if (!row || !column) return moveToNextCell(cell, 1);
                focusCell(row.nextElementSibling?.querySelector(`[data-column="${column}"]`) || cell);
            }

            function number(value) {
                const parsed = Number(String(value || '').replace(/[^0-9.-]/g, ''));
                return Number.isFinite(parsed) ? parsed : 0;
            }

            function format(value) {
                return value > 0 ? value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
            }

            function reindexRows() {
                editor.querySelectorAll('.abstract-item-row').forEach((row, rowIndex) => {
                    row.dataset.row = rowIndex;
                    row.querySelectorAll('.abstract-item-cell').forEach((cell) => cell.dataset.row = rowIndex);
                });
            }

            function rowHtml(index) {
                return `<tr class="abstract-item-row" data-row="${index}">
                    <td class="abstract-cell abstract-item-cell abstract-center" contenteditable="true" data-column="item_no" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-description-cell" contenteditable="true" data-column="name_of_goods_services" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-right" contenteditable="true" data-column="quantity" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-center" contenteditable="true" data-column="unit_of_measure" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" contenteditable="true" data-column="supplier_1_amount" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" contenteditable="true" data-column="supplier_2_amount" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" contenteditable="true" data-column="supplier_3_amount" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" contenteditable="true" data-column="supplier_4_amount" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-right supplier-amount" contenteditable="true" data-column="supplier_5_amount" data-row="${index}"></td>
                    <td class="abstract-cell abstract-item-cell abstract-right lowest-price-cell" contenteditable="true" data-column="total_lowest_price" data-row="${index}"></td>
                </tr>`;
            }

            window.addAbstractRow = function () {
                const rows = editor.querySelectorAll('.abstract-item-row');
                rows[rows.length - 1]?.insertAdjacentHTML('afterend', rowHtml(rows.length));
                reindexRows();
            };

            window.removeEmptyAbstractRows = function () {
                Array.from(editor.querySelectorAll('.abstract-item-row')).slice(22).forEach((row) => {
                    const hasValue = Array.from(row.querySelectorAll('.abstract-item-cell')).some((cell) => cell.innerText.trim() !== '');
                    if (!hasValue) row.remove();
                });
                reindexRows();
                recalcAll();
            };

            function recalcRow(row) {
                const amounts = supplierColumns
                    .map((column) => number(row.querySelector(`[data-column="${column}"]`)?.innerText))
                    .filter((value) => value > 0);
                const lowest = row.querySelector('[data-column="total_lowest_price"]');

                if (lowest && !lowest.dataset.manual) {
                    lowest.innerText = amounts.length ? format(Math.min(...amounts)) : '';
                }
            }

            function recalcAll() {
                const supplierTotals = [0, 0, 0, 0, 0];
                let lowestTotal = 0;

                editor.querySelectorAll('.abstract-item-row').forEach((row) => {
                    recalcRow(row);
                    supplierColumns.forEach((column, index) => {
                        supplierTotals[index] += number(row.querySelector(`[data-column="${column}"]`)?.innerText);
                    });
                    lowestTotal += number(row.querySelector('[data-column="total_lowest_price"]')?.innerText);
                });

                supplierTotals.forEach((total, index) => {
                    const cell = editor.querySelector(`[data-field="supplier_${index + 1}_total"]`);
                    if (cell && !cell.dataset.manual) cell.innerText = format(total);
                });

                const lowestTotalCell = editor.querySelector('[data-field="lowest_total_amount"]');
                if (lowestTotalCell && !lowestTotalCell.dataset.manual) lowestTotalCell.innerText = format(lowestTotal);

                const lowestSupplierField = editor.querySelector('[data-field="lowest_supplier_name"]');
                if (lowestSupplierField && !lowestSupplierField.dataset.manual) {
                    const positiveTotals = supplierTotals.map((value, index) => ({ value, index })).filter((entry) => entry.value > 0);
                    if (positiveTotals.length) {
                        const lowest = positiveTotals.sort((a, b) => a.value - b.value)[0];
                        lowestSupplierField.innerText = editor.querySelector(`[data-field="supplier_${lowest.index + 1}_name"]`)?.innerText.trim() || '';
                    }
                }
            }

            function collectItems() {
                return Array.from(editor.querySelectorAll('.abstract-item-row')).map((row) => {
                    const item = {};
                    columns.forEach((column) => item[column] = row.querySelector(`[data-column="${column}"]`)?.innerText.trim() || '');
                    return item;
                }).filter((item) => Object.values(item).some((value) => value !== ''));
            }

            function cleanSupplierHeaderText(value) {
                return String(value || '')
                    .split('\n')
                    .map((line) => line.trim())
                    .filter((line) => line !== '' && !/^[1-5]$/.test(line) && line.toUpperCase() !== 'AMOUNT / UNIT')
                    .join(' ')
                    .trim();
            }

            function fieldValue(fieldName) {
                const fieldCell = editor.querySelector(`[data-field="${fieldName}"]`);
                const value = fieldCell?.innerText.trim() || '';
                return /^supplier_[1-5]_name$/.test(fieldName) ? cleanSupplierHeaderText(value) : value;
            }

            function collectSuppliers() {
                const suppliers = {};
                for (let index = 1; index <= 5; index++) {
                    suppliers[`supplier_${index}_name`] = fieldValue(`supplier_${index}_name`);
                    suppliers[`supplier_${index}_total`] = editor.querySelector(`[data-field="supplier_${index}_total"]`)?.innerText.trim() || '';
                }
                return suppliers;
            }

            function collectAwards() {
                return Array.from(editor.querySelectorAll('[data-award-row]')).map((cell, index) => ({
                    row: index + 1,
                    text: cell.innerText.trim()
                })).filter((award) => award.text !== '');
            }

            function collectCommittee() {
                const committee = {};
                editor.querySelectorAll('[data-committee-role]').forEach((cell) => {
                    const role = cell.dataset.committeeRole;
                    const designationCell = editor.querySelector(`[data-committee-designation="${role}"]`);

                    committee[role] = {
                        name: cell.innerText.trim(),
                        designation: designationCell?.innerText.trim() || '',
                    };
                });
                return committee;
            }

            window.prepareAbstractPrint = function () {
                const printPaper = document.getElementById('abstractPrintPaper');
                if (!printPaper) return;
                const clone = editor.cloneNode(true);
                clone.querySelectorAll('[contenteditable]').forEach((el) => el.removeAttribute('contenteditable'));
                printPaper.innerHTML = '';
                printPaper.appendChild(clone);
            };

            window.printAbstractDocument = function () {
                window.prepareAbstractPrint();
                setTimeout(() => window.print(), 100);
            };

            function collectAbstractData() {
                recalcAll();
                const clone = editor.cloneNode(true);
                clone.querySelectorAll('[contenteditable]').forEach((el) => el.removeAttribute('contenteditable'));
                document.getElementById('abstract_document_html').value = clone.outerHTML.trim();
                document.getElementById('abstract_document_text').value = editor.innerText.trim();
                document.getElementById('abstract_items_json').value = JSON.stringify(collectItems());
                document.getElementById('abstract_suppliers_json').value = JSON.stringify(collectSuppliers());
                document.getElementById('abstract_awards_json').value = JSON.stringify(collectAwards());
                document.getElementById('abstract_committee_json').value = JSON.stringify(collectCommittee());

                form.querySelectorAll('[data-hidden-field]').forEach((input) => {
                    input.value = fieldValue(input.dataset.hiddenField);
                });
            }

            document.addEventListener('keydown', function (event) {
                const cell = event.target.closest('.abstract-cell');
                if (!cell || !editor.contains(cell)) return;
                if (event.key === 'Tab') {
                    event.preventDefault();
                    moveToNextCell(cell, event.shiftKey ? -1 : 1);
                }
                if (event.key === 'Enter') {
                    event.preventDefault();
                    moveBelow(cell);
                }
            });

            document.addEventListener('paste', function (event) {
                const cell = event.target.closest('.abstract-item-cell');
                if (!cell || !editor.contains(cell)) return;
                const text = event.clipboardData?.getData('text/plain') || '';
                if (!text.includes('\t') && !text.includes('\n')) return;
                event.preventDefault();
                const startRow = Number(cell.dataset.row || 0);
                const startColumn = columns.indexOf(cell.dataset.column);
                text.replace(/\r/g, '').split('\n').filter((line) => line.length).forEach((line, rowOffset) => {
                    while (editor.querySelectorAll('.abstract-item-row').length <= startRow + rowOffset) window.addAbstractRow();
                    const row = editor.querySelectorAll('.abstract-item-row')[startRow + rowOffset];
                    line.split('\t').forEach((value, colOffset) => {
                        const target = row.querySelector(`[data-column="${columns[startColumn + colOffset]}"]`);
                        if (target) target.innerText = value.trim();
                    });
                    recalcRow(row);
                });
                recalcAll();
            });

            document.addEventListener('input', function (event) {
                const cell = event.target.closest('.abstract-cell');
                if (!cell || !editor.contains(cell)) return;
                if (cell.classList.contains('lowest-price-cell') || cell.closest('.abstract-total-row')) cell.dataset.manual = '1';
                if (cell.classList.contains('supplier-amount')) {
                    recalcRow(cell.closest('.abstract-item-row'));
                    recalcAll();
                }
            });

            form?.addEventListener('submit', collectAbstractData);
            window.addEventListener('beforeprint', window.prepareAbstractPrint);
            setEditableState();
        })();
    </script>
@else
    <script>
        window.prepareAbstractPrint = function () {
            const editor = document.getElementById('abstractEditor');
            const printPaper = document.getElementById('abstractPrintPaper');
            if (!editor || !printPaper) return;
            const clone = editor.cloneNode(true);
            clone.querySelectorAll('[contenteditable]').forEach((el) => el.removeAttribute('contenteditable'));
            printPaper.innerHTML = '';
            printPaper.appendChild(clone);
        };
        window.printAbstractDocument = function () {
            window.prepareAbstractPrint();
            setTimeout(() => window.print(), 100);
        };
        window.addEventListener('beforeprint', window.prepareAbstractPrint);
    </script>
@endif
