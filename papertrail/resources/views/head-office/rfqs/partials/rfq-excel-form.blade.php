@php
    $mode = $mode ?? 'show';
    $isEditable = in_array($mode, ['create', 'edit'], true);
    $field = fn (string $name, mixed $default = '') => old($name, filled($rfq->{$name} ?? null) ? $rfq->{$name} : $default);
    $money = fn ($value) => filled($value) ? number_format((float) $value, 2, '.', '') : '';
    $text = fn ($value, string $fallback = '') => e(filled($value) ? $value : $fallback);
    $rfqDate = old('rfq_date', $rfq->rfq_date?->format('Y-m-d') ?? now()->toDateString());
    $items = collect(old('items_json') ? json_decode(old('items_json'), true) : null);

    if ($items->isEmpty()) {
        if (is_array($rfq->items_json)) {
            $items = collect($rfq->items_json);
        } elseif ($rfq->relationLoaded('items') || $rfq->exists) {
            $items = $rfq->items->map(fn ($item) => [
                'item_no' => $item->item_no,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_of_issue' => $item->unit_of_issue,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
            ]);
        }
    }

    $filledItemCount = $items->filter(function ($item) {
        return collect((array) $item)->some(fn ($value) => trim((string) ($value ?? '')) !== '');
    })->count();
    $rowCount = match (true) {
        $filledItemCount <= 8 => 18,
        $filledItemCount <= 14 => 20,
        $filledItemCount <= 20 => 24,
        default => $filledItemCount + 4,
    };
    $savedHtml = old('document_html', $rfq->document_html);
    $officialHeaderRow = trim(view('rfqs.partials.official-header-row')->render());
    $upgradeOfficialHeader = function (?string $html) use ($officialHeaderRow) {
        if (! filled($html) || str_contains($html, 'rfq-official-header-row')) {
            return $html;
        }

        $updated = preg_replace(
            '/<tr>\s*<td\b[^>]*data-field=(["\'])header_1\1[\s\S]*?<\/tr>\s*<tr>\s*<td\b[^>]*data-field=(["\'])header_2\2[\s\S]*?<\/tr>\s*<tr>\s*<td\b[^>]*data-field=(["\'])header_3\3[\s\S]*?<\/tr>\s*<tr>\s*<td\b[^>]*class=(["\'])[^"\']*rfq-no-border[^"\']*\4[^>]*>\s*(?:&nbsp;|\s)*<\/td>\s*<\/tr>/i',
            $officialHeaderRow,
            $html,
            1
        );

        return $updated ?? $html;
    };
    $blankItemRowHtml = function (int $index): string {
        return '<tr class="rfq-item-row" data-row="' . $index . '">'
            . '<td class="rfq-cell rfq-item-cell rfq-center" data-column="item_no" data-row="' . $index . '"></td>'
            . '<td class="rfq-cell rfq-item-cell rfq-description-cell" data-column="description" data-row="' . $index . '"></td>'
            . '<td class="rfq-cell rfq-item-cell rfq-number" data-column="quantity" data-row="' . $index . '"></td>'
            . '<td class="rfq-cell rfq-item-cell rfq-center" data-column="unit_of_issue" data-row="' . $index . '"></td>'
            . '<td class="rfq-cell rfq-item-cell rfq-money" data-column="unit_price" data-row="' . $index . '"></td>'
            . '<td class="rfq-cell rfq-item-cell rfq-money total-cell" data-column="total" data-row="' . $index . '"></td>'
            . '</tr>';
    };
    $trimTrailingBlankRows = function (?string $html, int $targetRows): ?string {
        if (! filled($html) || ! preg_match_all('/<tr\b(?=[^>]*\brfq-item-row\b)[^>]*>[\s\S]*?<\/tr>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $rows = $matches[0];
        $rowTotal = count($rows);

        if ($rowTotal <= $targetRows) {
            return $html;
        }

        for ($index = $rowTotal - 1; $index >= 0 && $rowTotal > $targetRows; $index--) {
            [$rowHtml, $offset] = $rows[$index];
            $plainText = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($rowHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

            if ($plainText !== '') {
                break;
            }

            $html = substr_replace($html, '', $offset, strlen($rowHtml));
            $rowTotal--;
        }

        return $html;
    };
    $normalizeItemRows = function (?string $html, int $targetRows) use ($trimTrailingBlankRows, $blankItemRowHtml): ?string {
        $html = $trimTrailingBlankRows($html, $targetRows);

        if (! filled($html) || ! preg_match_all('/<tr\b(?=[^>]*\brfq-item-row\b)[^>]*>[\s\S]*?<\/tr>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $rows = $matches[0];
        $rowTotal = count($rows);

        if ($rowTotal >= $targetRows) {
            return $html;
        }

        [$lastRowHtml, $lastOffset] = $rows[$rowTotal - 1];
        $extraRows = '';

        for ($index = $rowTotal; $index < $targetRows; $index++) {
            $extraRows .= $blankItemRowHtml($index);
        }

        return substr_replace($html, $extraRows, $lastOffset + strlen($lastRowHtml), 0);
    };
    $renderedHtml = $normalizeItemRows($upgradeOfficialHeader($savedHtml), $rowCount);

    if (filled($renderedHtml) && ! $isEditable) {
        $renderedHtml = preg_replace('/\scontenteditable(=("|\').*?\2|=[^\s>]*)?/i', '', $renderedHtml);
    }

    $deliveryPeriodDefault = 'Delivery period within ______ calendar days.';
    $warrantyDefault = 'Warranty shall be a period of six (6) months for supplies and materials. One (1) year for equipment, from date of acceptance.';
    $priceValidityDefault = 'Price validity shall be for a period of sixty (60) calendar days.';
    $philgepsRequirementDefault = 'PhilGEPS Registration Certificate/Number shall be provided in the quotation.';
    $mayorsPermitRequirementDefault = "Mayor's Business Permit shall be attached upon submission of the quotation.";

    $deliveryPeriodText = old('delivery_period', $rfq->delivery_period ?: $deliveryPeriodDefault);
    $warrantyText = old('warranty_text', $rfq->warranty_text ?: $warrantyDefault);
    $priceValidityText = old('price_validity_text', $rfq->price_validity_text ?: $priceValidityDefault);
    $philgepsRequirementText = old('philgeps_requirement_text', $rfq->philgeps_requirement_text ?: $philgepsRequirementDefault);
    $mayorsPermitRequirementText = old('mayors_permit_requirement_text', $rfq->mayors_permit_requirement_text ?: $mayorsPermitRequirementDefault);
@endphp

@if ($isEditable)
    <input type="hidden" name="source_pr_document_id" value="{{ old('source_pr_document_id', $rfq->source_pr_document_id ?? $sourceDocument?->id) }}">
    <input type="hidden" name="source_bac_resolution_id" value="{{ old('source_bac_resolution_id', $rfq->source_bac_resolution_id ?? $sourceResolution?->id) }}">
    <input type="hidden" name="document_html" id="rfq_document_html">
    <input type="hidden" name="document_text" id="rfq_document_text">
    <input type="hidden" name="items_json" id="rfq_items_json">
    @foreach ([
        'rfq_number', 'rfq_date', 'supplier_name', 'supplier_address', 'procurement_officer_name',
        'procurement_officer_designation', 'abc_amount', 'purpose', 'delivery_period', 'warranty_text',
        'price_validity_text', 'philgeps_requirement_text', 'mayors_permit_requirement_text'
    ] as $hiddenField)
        <input type="hidden" name="{{ $hiddenField }}" data-hidden-field="{{ $hiddenField }}">
    @endforeach
@endif

<div class="rfq-document-wrap">
    <section class="rfq-a4-page">
        <div class="rfq-document" id="rfqEditorContainer">
        @if (filled($renderedHtml))
            {!! $renderedHtml !!}
        @else
            <table id="rfqEditor" class="rfq-excel-form">
                <colgroup>
                    <col style="width:10%">
                    <col style="width:40%">
                    <col style="width:10%">
                    <col style="width:14%">
                    <col style="width:13%">
                    <col style="width:13%">
                </colgroup>
                <tbody>
                    @include('rfqs.partials.official-header-row')
                    <tr><td colspan="6" class="rfq-cell rfq-title rfq-no-border" data-field="rfq_title">REQUEST FOR QUOTATION</td></tr>
                    <tr>
                        <td colspan="3" class="rfq-no-border"></td>
                        <td class="rfq-no-border rfq-label">Date:</td>
                        <td colspan="2" class="rfq-cell rfq-bottom-line" data-field="rfq_date">{!! $text($rfqDate) !!}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="rfq-no-border"></td>
                        <td class="rfq-no-border rfq-label">Quotation No.:</td>
                        <td colspan="2" class="rfq-cell rfq-bottom-line" data-field="rfq_number">{!! $text($field('rfq_number')) !!}</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="rfq-cell rfq-no-border rfq-bottom-line" data-field="supplier_name">{!! $text($field('supplier_name')) !!}</td>
                        <td colspan="4" class="rfq-no-border"></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="rfq-cell rfq-no-border rfq-bottom-line" data-field="supplier_address">{!! $text($field('supplier_address')) !!}</td>
                        <td colspan="4" class="rfq-no-border"></td>
                    </tr>
                    <tr>
                        <td colspan="6" class="rfq-cell rfq-instruction" data-field="instruction_text">Please quote your lowest price on the item listed below, subject to the General Conditions stated herein under, stating the shortest time of delivery and submit your quotation duly signed by your representative not later than ____________, in the return envelope attached herewith.</td>
                    </tr>
                    <tr><td colspan="6" class="rfq-no-border">&nbsp;</td></tr>
                    <tr>
                        <td colspan="3" class="rfq-no-border"></td>
                        <td colspan="3" class="rfq-cell rfq-no-border rfq-center rfq-bold rfq-bottom-line" data-field="procurement_officer_name">{!! $text($field('procurement_officer_name', 'ROLAND P. FELICILDA')) !!}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="rfq-no-border"></td>
                        <td colspan="3" class="rfq-cell rfq-no-border rfq-center" data-field="procurement_officer_designation">{!! $text($field('procurement_officer_designation', 'Procurement Officer')) !!}</td>
                    </tr>
                    <tr class="rfq-term-row">
                        <td class="rfq-term-label">TERM</td>
                        <td colspan="5"><span class="rfq-term-number">1.</span><span class="rfq-cell rfq-inline-editable" data-field="delivery_period">{{ $deliveryPeriodText }}</span></td>
                    </tr>
                    <tr class="rfq-term-row">
                        <td></td>
                        <td colspan="5"><span class="rfq-term-number">2.</span><span class="rfq-cell rfq-inline-editable" data-field="warranty_text">{{ $warrantyText }}</span></td>
                    </tr>
                    <tr class="rfq-term-row">
                        <td></td>
                        <td colspan="5"><span class="rfq-term-number">3.</span><span class="rfq-cell rfq-inline-editable" data-field="price_validity_text">{{ $priceValidityText }}</span></td>
                    </tr>
                    <tr class="rfq-term-row">
                        <td></td>
                        <td colspan="5"><span class="rfq-term-number">4.</span><span class="rfq-cell rfq-inline-editable" data-field="philgeps_requirement_text">{{ $philgepsRequirementText }}</span></td>
                    </tr>
                    <tr class="rfq-term-row">
                        <td></td>
                        <td colspan="5"><span class="rfq-term-number">5.</span><span class="rfq-cell rfq-inline-editable" data-field="mayors_permit_requirement_text">{{ $mayorsPermitRequirementText }}</span></td>
                    </tr>
                    <tr class="rfq-meta-row">
                        <td class="rfq-bold">ABC:</td>
                        <td colspan="5" class="rfq-cell rfq-money rfq-bottom-line" data-field="abc_amount">{!! $text($money($field('abc_amount'))) !!}</td>
                    </tr>
                    <tr class="rfq-meta-row">
                        <td class="rfq-bold">Purpose:</td>
                        <td colspan="5" class="rfq-cell rfq-bottom-line" data-field="purpose">{!! $text($field('purpose')) !!}</td>
                    </tr>
                    <tr class="rfq-items-header rfq-item-heading">
                        <th>ITEM NO.</th>
                        <th>DESCRIPTION</th>
                        <th>QTY</th>
                        <th>UNIT OF ISSUE</th>
                        <th>UNIT PRICE</th>
                        <th>TOTAL</th>
                    </tr>
                    @for ($index = 0; $index < $rowCount; $index++)
                        @php $item = $items->get($index, []); @endphp
                        <tr class="rfq-item-row" data-row="{{ $index }}">
                            <td class="rfq-cell rfq-item-cell rfq-center" data-column="item_no" data-row="{{ $index }}">{!! $text($item['item_no'] ?? '') !!}</td>
                            <td class="rfq-cell rfq-item-cell rfq-description-cell" data-column="description" data-row="{{ $index }}">{!! $text($item['description'] ?? '') !!}</td>
                            <td class="rfq-cell rfq-item-cell rfq-number" data-column="quantity" data-row="{{ $index }}">{!! $text($money($item['quantity'] ?? null)) !!}</td>
                            <td class="rfq-cell rfq-item-cell rfq-center" data-column="unit_of_issue" data-row="{{ $index }}">{!! $text($item['unit_of_issue'] ?? '') !!}</td>
                            <td class="rfq-cell rfq-item-cell rfq-money" data-column="unit_price" data-row="{{ $index }}">{!! $text($money($item['unit_price'] ?? null)) !!}</td>
                            <td class="rfq-cell rfq-item-cell rfq-money total-cell" data-column="total" data-row="{{ $index }}">{!! $text($money($item['total'] ?? null)) !!}</td>
                        </tr>
                    @endfor
                    <tr><td colspan="6" class="rfq-no-border">&nbsp;</td></tr>
                    <tr>
                        <td colspan="6" class="rfq-cell rfq-no-border rfq-center" data-field="final_statement">After having carefully read and accepted your General Condition Below, I/We quote you on items and prices noted above.</td>
                    </tr>
                    <tr class="rfq-signature-row">
                        <td colspan="2" class="rfq-cell rfq-no-border rfq-bottom-line rfq-center" data-field="printed_name_signature"></td>
                        <td class="rfq-no-border"></td>
                        <td colspan="3" class="rfq-cell rfq-no-border rfq-bottom-line rfq-center" data-field="philgeps_type">PhilGeps Type of Registration:</td>
                    </tr>
                    <tr class="rfq-signature-row">
                        <td colspan="2" class="rfq-no-border rfq-center">Printed Name &amp; Signature</td>
                        <td class="rfq-no-border"></td>
                        <td colspan="3" class="rfq-no-border rfq-center">PhilGeps Type of Registration</td>
                    </tr>
                    <tr class="rfq-signature-row">
                        <td colspan="2" class="rfq-cell rfq-no-border rfq-bottom-line rfq-center" data-field="contact_number"></td>
                        <td class="rfq-no-border"></td>
                        <td colspan="3" class="rfq-cell rfq-no-border rfq-bottom-line rfq-center" data-field="philgeps_number"></td>
                    </tr>
                    <tr class="rfq-signature-row">
                        <td colspan="2" class="rfq-no-border rfq-center">Tel No. / Cellphone No.</td>
                        <td class="rfq-no-border"></td>
                        <td colspan="3" class="rfq-no-border rfq-center">PhilGeps Registration Number</td>
                    </tr>
                    <tr class="rfq-signature-row">
                        <td colspan="2" class="rfq-cell rfq-no-border rfq-bottom-line rfq-center" data-field="tin_number"></td>
                        <td class="rfq-no-border"></td>
                        <td colspan="3" class="rfq-cell rfq-no-border rfq-bottom-line rfq-center" data-field="supplier_date"></td>
                    </tr>
                    <tr class="rfq-signature-row">
                        <td colspan="2" class="rfq-no-border rfq-center">TIN No.</td>
                        <td class="rfq-no-border"></td>
                        <td colspan="3" class="rfq-no-border rfq-center">Date</td>
                    </tr>
                </tbody>
            </table>
        @endif
        </div>
    </section>
</div>

<div id="rfqPrintArea" class="rfq-print-area">
    <section id="rfqPrintPaper" class="rfq-a4-page">
        <div class="rfq-document" id="rfqPrintContainer"></div>
    </section>
</div>

@if ($isEditable)
    <script>
        (function () {
            window.PaperTrailRowTools = window.PaperTrailRowTools || (() => {
                function normalizeCellText(value) {
                    return (value || '')
                        .replace(/\u00a0/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();
                }

                function getCellValue(cell) {
                    if (!cell) return '';

                    if (cell.matches('input, textarea, select')) {
                        return normalizeCellText(cell.value);
                    }

                    return normalizeCellText(cell.innerText || cell.textContent || '');
                }

                function isRowEmpty(row, fieldSelector = 'input:not([type="hidden"]):not([readonly]), textarea:not([readonly]), select:not([disabled]), [contenteditable="true"], .pr-excel-cell, .pr-cell, .rfq-cell, .abstract-cell') {
                    if (!row) return true;

                    const fields = Array.from(row.querySelectorAll(fieldSelector))
                        .filter((field) => !field.matches('input[type="hidden"], [readonly], [disabled]'));

                    if (!fields.length) {
                        return normalizeCellText(row.innerText || row.textContent || '') === '';
                    }

                    return fields.every((field) => getCellValue(field) === '');
                }

                function removeEmptyRows(rowSelector, minimumRows = 1, fieldSelector) {
                    let removed = 0;

                    Array.from(document.querySelectorAll(rowSelector)).forEach((row) => {
                        if (document.querySelectorAll(rowSelector).length <= minimumRows) {
                            return;
                        }

                        if (isRowEmpty(row, fieldSelector)) {
                            row.remove();
                            removed++;
                        }
                    });

                    return removed;
                }

                function removeSelectedEmptyRow(row, rowSelector, minimumRows = 1, fieldSelector) {
                    if (!row) return false;

                    if (document.querySelectorAll(rowSelector).length <= minimumRows) {
                        window.PaperTrailDialog?.notice('At least one row must remain.', {
                            title: 'Row Required',
                        });
                        return false;
                    }

                    if (!isRowEmpty(row, fieldSelector)) {
                        window.PaperTrailDialog?.notice('This row has content and cannot be removed. Please clear the row first before removing it.', {
                            title: 'Row Has Content',
                        });
                        return false;
                    }

                    row.remove();
                    return true;
                }

                return { normalizeCellText, getCellValue, isRowEmpty, removeEmptyRows, removeSelectedEmptyRow };
            })();

            const editor = document.getElementById('rfqEditor');
            const form = document.querySelector('.rfq-editor-form');
            if (!editor) return;
            const columns = ['item_no', 'description', 'quantity', 'unit_of_issue', 'unit_price', 'total'];
            const minimumOfficialRows = {{ $rowCount }};

            function setEditableState() {
                editor.querySelectorAll('.rfq-cell').forEach((cell) => cell.setAttribute('contenteditable', 'true'));
                reindexRows();
            }

            function cells() {
                return Array.from(editor.querySelectorAll('.rfq-cell[contenteditable="true"]'));
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
                const row = cell.closest('.rfq-item-row');
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
                editor.querySelectorAll('.rfq-item-row').forEach((row, rowIndex) => {
                    row.dataset.row = rowIndex;
                    row.querySelectorAll('.rfq-item-cell').forEach((cell) => cell.dataset.row = rowIndex);
                });
            }

            function rowHtml(index) {
                return `<tr class="rfq-item-row" data-row="${index}">
                    <td class="rfq-cell rfq-item-cell rfq-center" contenteditable="true" data-column="item_no" data-row="${index}"></td>
                    <td class="rfq-cell rfq-item-cell rfq-description-cell" contenteditable="true" data-column="description" data-row="${index}"></td>
                    <td class="rfq-cell rfq-item-cell rfq-number" contenteditable="true" data-column="quantity" data-row="${index}"></td>
                    <td class="rfq-cell rfq-item-cell rfq-center" contenteditable="true" data-column="unit_of_issue" data-row="${index}"></td>
                    <td class="rfq-cell rfq-item-cell rfq-money" contenteditable="true" data-column="unit_price" data-row="${index}"></td>
                    <td class="rfq-cell rfq-item-cell rfq-money total-cell" contenteditable="true" data-column="total" data-row="${index}"></td>
                </tr>`;
            }

            window.addRfqRow = function () {
                const rows = editor.querySelectorAll('.rfq-item-row');
                rows[rows.length - 1]?.insertAdjacentHTML('afterend', rowHtml(rows.length));
                reindexRows();
            };

            window.removeEmptyRfqRows = function () {
                let removed = 0;

                Array.from(editor.querySelectorAll('.rfq-item-row')).forEach((row) => {
                    if (editor.querySelectorAll('.rfq-item-row').length <= minimumOfficialRows) {
                        return;
                    }

                    if (window.PaperTrailRowTools.isRowEmpty(row, '.rfq-item-cell')) {
                        row.remove();
                        removed++;
                    }
                });

                if (removed === 0) {
                    window.PaperTrailDialog?.notice('No empty RFQ rows to remove.', {
                        title: 'No Empty Rows',
                    });
                }

                reindexRows();
            };

            function recalcRow(row) {
                const quantity = number(row.querySelector('[data-column="quantity"]')?.innerText);
                const unitPrice = number(row.querySelector('[data-column="unit_price"]')?.innerText);
                const total = row.querySelector('[data-column="total"]');
                if (total && !total.dataset.manual && (quantity > 0 || unitPrice > 0)) total.innerText = format(quantity * unitPrice);
            }

            function collectItems() {
                return Array.from(editor.querySelectorAll('.rfq-item-row')).filter((row) => {
                    return !window.PaperTrailRowTools.isRowEmpty(row, '.rfq-item-cell');
                }).map((row) => {
                    const item = {};
                    columns.forEach((column) => item[column] = window.PaperTrailRowTools.getCellValue(row.querySelector(`[data-column="${column}"]`)));
                    return item;
                });
            }

            window.prepareRfqPrint = function () {
                const printContainer = document.getElementById('rfqPrintContainer');
                if (!printContainer) return;
                const clone = editor.cloneNode(true);
                clone.querySelectorAll('[contenteditable]').forEach((el) => el.removeAttribute('contenteditable'));
                printContainer.innerHTML = '';
                printContainer.appendChild(clone);
            };

            window.printRfqDocument = function () {
                window.prepareRfqPrint();
                setTimeout(() => window.print(), 100);
            };

            function collectRfqData() {
                const clone = editor.cloneNode(true);
                clone.querySelectorAll('[contenteditable]').forEach((el) => el.removeAttribute('contenteditable'));
                document.getElementById('rfq_document_html').value = clone.outerHTML.trim();
                document.getElementById('rfq_document_text').value = editor.innerText.trim();
                document.getElementById('rfq_items_json').value = JSON.stringify(collectItems());

                form.querySelectorAll('[data-hidden-field]').forEach((input) => {
                    const fieldCell = editor.querySelector(`[data-field="${input.dataset.hiddenField}"]`);
                    input.value = fieldCell?.innerText.trim() || '';
                });
            }

            document.addEventListener('keydown', function (event) {
                const cell = event.target.closest('.rfq-cell');
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
                const cell = event.target.closest('.rfq-item-cell');
                if (!cell || !editor.contains(cell)) return;
                const text = event.clipboardData?.getData('text/plain') || '';
                if (!text.includes('\t') && !text.includes('\n')) return;
                event.preventDefault();
                const startRow = Number(cell.dataset.row || 0);
                const startColumn = columns.indexOf(cell.dataset.column);
                text.replace(/\r/g, '').split('\n').filter((line) => line.length).forEach((line, rowOffset) => {
                    while (editor.querySelectorAll('.rfq-item-row').length <= startRow + rowOffset) window.addRfqRow();
                    const row = editor.querySelectorAll('.rfq-item-row')[startRow + rowOffset];
                    line.split('\t').forEach((value, colOffset) => {
                        const target = row.querySelector(`[data-column="${columns[startColumn + colOffset]}"]`);
                        if (target) target.innerText = value.trim();
                    });
                    recalcRow(row);
                });
            });

            document.addEventListener('input', function (event) {
                const cell = event.target.closest('.rfq-cell');
                if (!cell || !editor.contains(cell)) return;
                if (cell.dataset.column === 'total') cell.dataset.manual = '1';
                if (['quantity', 'unit_price'].includes(cell.dataset.column)) recalcRow(cell.closest('.rfq-item-row'));
            });

            form?.addEventListener('submit', collectRfqData);
            document.getElementById('removeEmptyRfqRowsBtn')?.addEventListener('click', window.removeEmptyRfqRows);
            window.addEventListener('beforeprint', window.prepareRfqPrint);
            setEditableState();
        })();
    </script>
@else
    <script>
        window.prepareRfqPrint = function () {
            const editor = document.getElementById('rfqEditor');
            const printContainer = document.getElementById('rfqPrintContainer');
            if (!editor || !printContainer) return;
            const clone = editor.cloneNode(true);
            clone.querySelectorAll('[contenteditable]').forEach((el) => el.removeAttribute('contenteditable'));
            printContainer.innerHTML = '';
            printContainer.appendChild(clone);
        };
        window.printRfqDocument = function () {
            window.prepareRfqPrint();
            setTimeout(() => window.print(), 100);
        };
        window.addEventListener('beforeprint', window.prepareRfqPrint);
    </script>
@endif
