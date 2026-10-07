@php
    $mode = $mode ?? 'show';
    $isEditable = in_array($mode, ['create', 'edit'], true);
    $sourceDocument = $sourceDocument ?? $po->sourcePrDocument;
    $field = fn (string $name, mixed $default = '') => old($name, filled($po->{$name} ?? null) ? $po->{$name} : $default);
    $money = fn ($value) => filled($value) ? number_format((float) $value, 2, '.', '') : '';
    $text = fn ($value, string $fallback = '') => e(filled($value) ? $value : $fallback);
    $sourceId = old('source_pr_document_id', $po->source_pr_document_id ?? $sourceDocument?->id);
    $poDate = old('po_date', $po->po_date?->format('Y-m-d'));
    $officialHeaderRows = trim(view('purchase-orders.partials.official-header-rows', ['colspan' => 7])->render());
    $officialTitleRows = $officialHeaderRows."\n".'<tr class="po-title-row"><td colspan="7" class="po-cell po-title-main" data-field="po_title">PURCHASE ORDER</td></tr>';
    $upgradeOfficialHeader = function (?string $html) use ($officialTitleRows) {
        if (! filled($html) || str_contains($html, 'po-official-header')) {
            return $html;
        }

        $updated = preg_replace(
            '/<tr\b[^>]*class=(["\'])po-spacer-row\1[^>]*>[\s\S]*?<\/tr>\s*<tr\b[^>]*class=(["\'])po-title-row\2[^>]*>[\s\S]*?PURCHASE ORDER[\s\S]*?<\/tr>\s*<tr\b[^>]*class=(["\'])po-title-row\3[^>]*>[\s\S]*?TOMAS OPPUS[\s\S]*?<\/tr>\s*<tr\b[^>]*class=(["\'])po-title-row\4[^>]*>[\s\S]*?LGU[\s\S]*?<\/tr>/i',
            $officialTitleRows,
            $html,
            1
        );

        return $updated ?? $html;
    };
    $savedHtml = $upgradeOfficialHeader(old('document_html', $po->document_html));
    $items = collect(old('items_json') ? json_decode(old('items_json'), true) : null);

    if ($items->isEmpty()) {
        if (is_array($po->items_json)) {
            $items = collect($po->items_json);
        } elseif ($po->relationLoaded('items') || $po->exists) {
            $items = $po->items->map(fn ($item) => [
                'item_no' => $item->item_no,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'description' => $item->description ?: $item->item_description,
                'unit_cost' => $item->unit_cost,
                'total_cost' => $item->total_cost,
            ]);
        }
    }

    $rowCount = max(30, $items->count());
    $penalty = $field('penalty_clause', 'In case of failure to make the full delivery within the time specified above, a penalty of one-tenth (1/10) of one percent for every day of delay shall be imposed.');
@endphp

@if ($isEditable)
    <input type="hidden" name="source_pr_document_id" value="{{ $sourceId }}">
    <input type="hidden" name="document_html" id="po_document_html">
    <input type="hidden" name="document_text" id="po_document_text">
    <input type="hidden" name="items_json" id="po_items_json">
    @foreach ([
        'po_number', 'po_date', 'supplier_name', 'supplier_address', 'mode_of_procurement', 'place_of_delivery',
        'date_of_delivery', 'delivery_term', 'payment_term', 'total_amount', 'total_amount_words', 'penalty_clause',
        'authorized_official_name', 'authorized_official_designation', 'supplier_representative_name',
        'supplier_representative_designation', 'supplier_conforme_date', 'fund_available_text', 'alobs_number',
        'alobs_amount', 'accountant_name', 'accountant_designation'
    ] as $hiddenField)
        <input type="hidden" name="{{ $hiddenField }}" data-hidden-field="{{ $hiddenField }}">
    @endforeach
@endif

<div class="po-document-wrap">
    <div class="po-a4-page po-document">
        @if (filled($savedHtml))
            @if (str_contains($savedHtml, 'id="purchaseOrderEditor"'))
                {!! $savedHtml !!}
            @else
                <div id="purchaseOrderEditor" class="po-legacy-document">
                    {!! $savedHtml !!}
                </div>
            @endif
        @else
            <table id="purchaseOrderEditor" class="po-excel-form">
                <colgroup>
                    <col style="width: 14%">
                    <col style="width: 14%">
                    <col style="width: 14%">
                    <col style="width: 16%">
                    <col style="width: 16%">
                    <col style="width: 13%">
                    <col style="width: 13%">
                </colgroup>
                <tbody id="poExcelBody">
                    <tr class="po-annex-row">
                        <td colspan="6" class="po-no-border"></td>
                        <td class="po-cell po-annex-cell" data-field="annex_number">Annex 29</td>
                    </tr>
                    @include('purchase-orders.partials.official-header-rows', ['colspan' => 7])
                    <tr class="po-title-row"><td colspan="7" class="po-cell po-title-main" data-field="po_title">PURCHASE ORDER</td></tr>
                    <tr class="po-section-line"><td colspan="7"></td></tr>

                    <tr>
                        <td class="po-label">Supplier :</td>
                        <td colspan="3" class="po-cell" data-field="supplier_name">{!! $text($field('supplier_name')) !!}</td>
                        <td class="po-label">P.O. No.:</td>
                        <td colspan="2" class="po-cell" data-field="po_number">{!! $text($field('po_number')) !!}</td>
                    </tr>
                    <tr>
                        <td class="po-label">Address :</td>
                        <td colspan="3" class="po-cell po-address-cell" data-field="supplier_address">{!! $text($field('supplier_address')) !!}</td>
                        <td class="po-label">Date :</td>
                        <td colspan="2" class="po-cell" data-field="po_date">{!! $text($poDate) !!}</td>
                    </tr>
                    <tr>
                        <td class="po-blank-cell"></td>
                        <td colspan="3" class="po-blank-cell"></td>
                        <td class="po-label">Mode of Procurement:</td>
                        <td colspan="2" class="po-cell" data-field="mode_of_procurement">{!! $text($field('mode_of_procurement')) !!}</td>
                    </tr>
                    <tr>
                        <td class="po-blank-cell"></td>
                        <td colspan="3" class="po-blank-cell"></td>
                        <td class="po-label">PR No./s:</td>
                        <td colspan="2" class="po-cell" data-field="pr_numbers">{!! $text($sourceDocument?->tracking_number) !!}</td>
                    </tr>

                    <tr><td colspan="7" class="po-label po-gentlemen-label">Gentlemen:</td></tr>
                    <tr><td colspan="7" class="po-cell po-centered-text po-gentlemen-text" data-field="gentlemen_text">Please furnish this office the following articles subject to the terms and conditions contained herein:</td></tr>

                    <tr>
                        <td class="po-label" colspan="2">Place of Delivery:</td>
                        <td colspan="3" class="po-cell" data-field="place_of_delivery">{!! $text($field('place_of_delivery', $po->delivery_place)) !!}</td>
                        <td class="po-label">Delivery Term:</td>
                        <td class="po-cell" data-field="delivery_term">{!! $text($field('delivery_term', $po->delivery_terms)) !!}</td>
                    </tr>
                    <tr>
                        <td class="po-label" colspan="2">Date of Delivery:</td>
                        <td colspan="3" class="po-cell" data-field="date_of_delivery">{!! $text($field('date_of_delivery', $po->delivery_date?->format('M d, Y'))) !!}</td>
                        <td class="po-label">Payment Term:</td>
                        <td class="po-cell" data-field="payment_term">{!! $text($field('payment_term', $po->payment_terms)) !!}</td>
                    </tr>

                    <tr class="po-items-header po-item-heading-row">
                        <th>ITEM NO.</th>
                        <th>QTY</th>
                        <th>UNIT</th>
                        <th colspan="2">DESCRIPTION</th>
                        <th>UNIT COST</th>
                        <th>TOTAL</th>
                    </tr>

                    @for ($index = 0; $index < $rowCount; $index++)
                        @php $item = $items->get($index, []); @endphp
                        <tr class="po-item-row" data-row="{{ $index }}">
                            <td class="po-cell po-item-cell po-center" data-column="item_no" data-row="{{ $index }}">{!! $text($item['item_no'] ?? '') !!}</td>
                            <td class="po-cell po-item-cell po-number" data-column="quantity" data-row="{{ $index }}">{!! $text($money($item['quantity'] ?? null)) !!}</td>
                            <td class="po-cell po-item-cell po-center" data-column="unit" data-row="{{ $index }}">{!! $text($item['unit'] ?? '') !!}</td>
                            <td colspan="2" class="po-cell po-item-cell description-cell" data-column="description" data-row="{{ $index }}">{!! $text($item['description'] ?? '') !!}</td>
                            <td class="po-cell po-item-cell po-money" data-column="unit_cost" data-row="{{ $index }}">{!! $text($money($item['unit_cost'] ?? null)) !!}</td>
                            <td class="po-cell po-item-cell po-money total-cell" data-column="total_cost" data-row="{{ $index }}">{!! $text($money($item['total_cost'] ?? null)) !!}</td>
                        </tr>
                    @endfor

                    <tr>
                        <td colspan="2" class="po-label">(Total Amount in Words)</td>
                        <td colspan="3" class="po-cell po-total-words-cell" data-field="total_amount_words">{!! $text($field('total_amount_words')) !!}</td>
                        <td class="po-total-label">Total:</td>
                        <td class="po-cell po-money po-grand-total" data-field="total_amount">{!! $text($money($field('total_amount'))) !!}</td>
                    </tr>
                    <tr>
                        <td colspan="7" class="po-cell po-penalty" data-field="penalty_clause">{!! $text($penalty) !!}</td>
                    </tr>
                    <tr class="po-signature-row">
                        <td colspan="3" class="po-signature-block">
                            <div>Conforme:</div>
                            <div class="po-sign-space"></div>
                            <div class="po-cell po-sign-name" data-field="supplier_representative_name">{!! $text($field('supplier_representative_name')) !!}</div>
                            <div class="po-sign-caption">(Signature over printed name)</div>
                            <div>Date: <span class="po-cell po-inline-cell" data-field="supplier_conforme_date">{!! $text($field('supplier_conforme_date')) !!}</span></div>
                        </td>
                        <td class="po-blank-cell"></td>
                        <td colspan="3" class="po-signature-block po-authorized-cell">
                            <div>Very truly yours,</div>
                            <div class="po-sign-space"></div>
                            <div class="po-cell po-sign-name" data-field="authorized_official_name">{!! $text($field('authorized_official_name')) !!}</div>
                            <div class="po-cell po-sign-caption" data-field="authorized_official_designation">{!! $text($field('authorized_official_designation', 'Authorized Official')) !!}</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="7" class="po-cell po-fund-label" data-field="fund_available_text">{!! $text($field('fund_available_text', 'Funds Available:')) !!}</td>
                    </tr>
                    <tr class="po-fund-row">
                        <td colspan="4" class="po-accountant-cell">
                            <div class="po-sign-space"></div>
                            <div class="po-cell po-sign-name" data-field="accountant_name">{!! $text($field('accountant_name')) !!}</div>
                            <div class="po-cell po-sign-caption" data-field="accountant_designation">{!! $text($field('accountant_designation', 'Municipal Accountant')) !!}</div>
                        </td>
                        <td class="po-label">ALOBS No.:</td>
                        <td colspan="2" class="po-cell" data-field="alobs_number">{!! $text($field('alobs_number')) !!}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="po-blank-cell"></td>
                        <td class="po-label">Amount:</td>
                        <td colspan="2" class="po-cell po-money" data-field="alobs_amount">{!! $text($money($field('alobs_amount'))) !!}</td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>
</div>

<div id="purchaseOrderPrintArea" class="po-print-area">
    <div id="purchaseOrderPrintPaper" class="po-a4-page po-document"></div>
</div>

@if ($isEditable)
    <script>
        (function () {
            const editor = document.getElementById('purchaseOrderEditor');
            const form = document.querySelector('.po-editor-form');
            if (!editor) return;

            const columns = ['item_no', 'quantity', 'unit', 'description', 'unit_cost', 'total_cost'];

            function setEditableState() {
                editor.querySelectorAll('.po-cell').forEach((cell) => cell.setAttribute('contenteditable', 'true'));
                reindexRows();
            }

            function cells() {
                return Array.from(editor.querySelectorAll('.po-cell[contenteditable="true"]'));
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

            function moveToNextPoCell(cell, offset) {
                const all = cells();
                focusCell(all[all.indexOf(cell) + offset] || cell);
            }

            function moveToCellBelow(cell) {
                const row = cell.closest('.po-item-row');
                const column = cell.dataset.column;
                if (!row || !column) return moveToNextPoCell(cell, 1);
                focusCell(row.nextElementSibling?.querySelector(`[data-column="${column}"]`) || cell);
            }

            function number(value) {
                const parsed = Number(String(value || '').replace(/[^0-9.-]/g, ''));
                return Number.isFinite(parsed) ? parsed : 0;
            }

            function normalizeCellText(value) {
                return String(value ?? '')
                    .replace(/\u00a0/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();
            }

            function getCellValue(cell) {
                if (!cell) return '';

                const value = cell.matches('input, textarea, select')
                    ? cell.value
                    : (cell.innerText || cell.textContent || '');

                return String(value ?? '').replace(/\u00a0/g, ' ').trim();
            }

            function isCellBlank(cell) {
                if (!cell) return true;

                if (cell.matches('input, textarea, select')) {
                    return normalizeCellText(cell.value) === '';
                }

                return normalizeCellText(cell.innerText || cell.textContent || '') === '';
            }

            function isPoRowEmpty(row) {
                if (!row) return true;

                const fields = row.querySelectorAll('input, textarea, select, [contenteditable="true"], .po-item-cell');

                if (!fields.length) {
                    return normalizeCellText(row.innerText || row.textContent || '') === '';
                }

                return Array.from(fields).every((field) => isCellBlank(field));
            }

            function removeEmptyRows(rowSelector, minimumRows = 1) {
                const rows = Array.from(editor.querySelectorAll(rowSelector));
                let removed = 0;

                rows.forEach((row) => {
                    const currentRows = editor.querySelectorAll(rowSelector);

                    if (currentRows.length <= minimumRows) return;

                    if (isPoRowEmpty(row)) {
                        row.remove();
                        removed++;
                    }
                });

                return removed;
            }

            function format(value) {
                return value > 0 ? value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
            }

            function reindexRows() {
                editor.querySelectorAll('.po-item-row').forEach((row, rowIndex) => {
                    row.dataset.row = rowIndex;
                    row.querySelectorAll('.po-item-cell').forEach((cell) => cell.dataset.row = rowIndex);
                });
            }

            function itemRowHtml(index) {
                return `<tr class="po-item-row" data-row="${index}">
                    <td class="po-cell po-item-cell po-center" contenteditable="true" data-column="item_no" data-row="${index}"></td>
                    <td class="po-cell po-item-cell po-number" contenteditable="true" data-column="quantity" data-row="${index}"></td>
                    <td class="po-cell po-item-cell po-center" contenteditable="true" data-column="unit" data-row="${index}"></td>
                    <td colspan="2" class="po-cell po-item-cell description-cell" contenteditable="true" data-column="description" data-row="${index}"></td>
                    <td class="po-cell po-item-cell po-money" contenteditable="true" data-column="unit_cost" data-row="${index}"></td>
                    <td class="po-cell po-item-cell po-money total-cell" contenteditable="true" data-column="total_cost" data-row="${index}"></td>
                </tr>`;
            }

            window.addPurchaseOrderRow = function () {
                const rows = editor.querySelectorAll('.po-item-row');
                rows[rows.length - 1]?.insertAdjacentHTML('afterend', itemRowHtml(rows.length));
                reindexRows();
            };

            window.removeEmptyPurchaseOrderRows = function () {
                const removed = removeEmptyRows('.po-item-row', 1);
                reindexRows();

                if (removed === 0) {
                    window.PaperTrailDialog?.notice('No empty Purchase Order rows to remove.', {
                        title: 'No Empty Rows',
                    });
                }
            };

            function recalcRow(row) {
                const qty = number(row.querySelector('[data-column="quantity"]')?.innerText);
                const unitCost = number(row.querySelector('[data-column="unit_cost"]')?.innerText);
                const totalCell = row.querySelector('[data-column="total_cost"]');
                if (totalCell && !totalCell.dataset.manual && (qty > 0 || unitCost > 0)) totalCell.innerText = format(qty * unitCost);
            }

            function recalcGrandTotal() {
                let total = 0;
                editor.querySelectorAll('[data-column="total_cost"]').forEach((cell) => total += number(cell.innerText));
                const grand = editor.querySelector('[data-field="total_amount"]');
                if (grand && !grand.dataset.manual) grand.innerText = format(total);
            }

            function collectItems() {
                return Array.from(editor.querySelectorAll('.po-item-row')).map((row) => {
                    const item = {};
                    columns.forEach((column) => item[column] = getCellValue(row.querySelector(`[data-column="${column}"]`)));
                    return item;
                }).filter((item) => Object.values(item).some((value) => normalizeCellText(value) !== ''));
            }

            window.preparePurchaseOrderPrint = function () {
                const printPaper = document.getElementById('purchaseOrderPrintPaper');
                if (!printPaper) return;
                const clone = editor.cloneNode(true);
                clone.querySelectorAll('[contenteditable]').forEach((el) => el.removeAttribute('contenteditable'));
                printPaper.innerHTML = '';
                printPaper.appendChild(clone);
            };

            window.printPurchaseOrderDocument = function () {
                window.preparePurchaseOrderPrint();
                setTimeout(() => window.print(), 100);
            };

            function collectPurchaseOrderData() {
                const clone = editor.cloneNode(true);
                clone.querySelectorAll('[contenteditable]').forEach((el) => el.removeAttribute('contenteditable'));
                document.getElementById('po_document_html').value = clone.outerHTML.trim();
                document.getElementById('po_document_text').value = editor.innerText.trim();
                document.getElementById('po_items_json').value = JSON.stringify(collectItems());

                form.querySelectorAll('[data-hidden-field]').forEach((input) => {
                    const fieldCell = editor.querySelector(`[data-field="${input.dataset.hiddenField}"]`);
                    input.value = getCellValue(fieldCell);
                });
            }

            document.getElementById('removeEmptyPoRowsBtn')?.addEventListener('click', window.removeEmptyPurchaseOrderRows);

            document.addEventListener('keydown', function (event) {
                const cell = event.target.closest('.po-cell');
                if (!cell || !editor.contains(cell)) return;

                if (event.key === 'Tab') {
                    event.preventDefault();
                    moveToNextPoCell(cell, event.shiftKey ? -1 : 1);
                }

                if (event.key === 'Enter') {
                    event.preventDefault();
                    moveToCellBelow(cell);
                }
            });

            document.addEventListener('paste', function (event) {
                const cell = event.target.closest('.po-item-cell');
                if (!cell || !editor.contains(cell)) return;
                const text = event.clipboardData?.getData('text/plain') || '';
                if (!text.includes('\t') && !text.includes('\n')) return;
                event.preventDefault();

                const startRow = Number(cell.dataset.row || 0);
                const startColumn = columns.indexOf(cell.dataset.column);
                text.replace(/\r/g, '').split('\n').filter((line) => line.length).forEach((line, rowOffset) => {
                    while (editor.querySelectorAll('.po-item-row').length <= startRow + rowOffset) window.addPurchaseOrderRow();
                    const row = editor.querySelectorAll('.po-item-row')[startRow + rowOffset];
                    line.split('\t').forEach((value, colOffset) => {
                        const target = row.querySelector(`[data-column="${columns[startColumn + colOffset]}"]`);
                        if (target) target.innerText = value.trim();
                    });
                    recalcRow(row);
                });
                recalcGrandTotal();
            });

            document.addEventListener('input', function (event) {
                const cell = event.target.closest('.po-cell');
                if (!cell || !editor.contains(cell)) return;
                if (cell.dataset.column === 'total_cost' || cell.dataset.field === 'total_amount') cell.dataset.manual = '1';
                if (['quantity', 'unit_cost', 'total_cost'].includes(cell.dataset.column)) {
                    recalcRow(cell.closest('.po-item-row'));
                    recalcGrandTotal();
                }
            });

            form?.addEventListener('submit', collectPurchaseOrderData);
            window.addEventListener('beforeprint', window.preparePurchaseOrderPrint);
            setEditableState();
        })();
    </script>
@else
    <script>
        window.preparePurchaseOrderPrint = function () {
            const editor = document.getElementById('purchaseOrderEditor');
            const printPaper = document.getElementById('purchaseOrderPrintPaper');
            if (!editor || !printPaper) return;
            const clone = editor.cloneNode(true);
            clone.querySelectorAll('[contenteditable]').forEach((el) => el.removeAttribute('contenteditable'));
            printPaper.innerHTML = '';
            printPaper.appendChild(clone);
        };
        window.printPurchaseOrderDocument = function () {
            window.preparePurchaseOrderPrint();
            setTimeout(() => window.print(), 100);
        };
        window.addEventListener('beforeprint', window.preparePurchaseOrderPrint);
        window.addEventListener('DOMContentLoaded', window.preparePurchaseOrderPrint);
        window.preparePurchaseOrderPrint();
    </script>
@endif
