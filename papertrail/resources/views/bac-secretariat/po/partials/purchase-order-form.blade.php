@php
    $mode = $mode ?? 'show';
    $isEditable = in_array($mode, ['create', 'edit'], true);
    $sourceDocument = $sourceDocument ?? $po->sourcePrDocument;

    $field = function (string $name, mixed $default = null) use ($po) {
        return old($name, $default ?? $po->{$name});
    };

    $poDate = old('po_date', $po->po_date?->format('Y-m-d'));
    $sourceId = old('source_pr_document_id', $po->source_pr_document_id ?? $sourceDocument?->id);
    $totalAmount = old('total_amount', $po->total_amount);
    $totalAmountWords = old('total_amount_words', $po->total_amount_words);
    $penaltyClause = old('penalty_clause', $po->penalty_clause ?: 'In case of failure to make the full delivery within the time specified above, a penalty of one-tenth (1/10) of one percent for every day of delay shall be imposed.');

    $oldItems = old('items');
    $itemRows = collect();

    if (is_array($oldItems)) {
        $itemRows = collect($oldItems)->values();
    } elseif ($po->relationLoaded('items') || $po->exists) {
        $itemRows = $po->items->map(fn ($item) => [
            'source_pr_item_id' => $item->source_pr_item_id,
            'item_no' => $item->item_no,
            'quantity' => $item->quantity,
            'unit' => $item->unit,
            'description' => $item->description ?: $item->item_description,
            'unit_cost' => $item->unit_cost,
            'total_cost' => $item->total_cost,
        ]);
    }

    $rowCount = max(20, $itemRows->count());
    $money = fn ($value) => filled($value) ? number_format((float) $value, 2, '.', '') : '';
    $text = fn ($value, string $fallback = '') => filled($value) ? $value : $fallback;
@endphp

@if ($isEditable)
    <input type="hidden" name="source_pr_document_id" value="{{ $sourceId }}">
@endif

<div class="po-document-wrap">
    <section class="po-a4-page">
        <article class="po-document">
            <div class="po-annex">Annex 29</div>

            <header class="po-header">
                <h1>PURCHASE ORDER</h1>
                <h2>TOMAS OPPUS</h2>
                <p>LGU</p>
            </header>

            <table class="po-table po-meta-table">
                <colgroup>
                    <col style="width: 14%">
                    <col style="width: 43%">
                    <col style="width: 16%">
                    <col style="width: 27%">
                </colgroup>
                <tr>
                    <td class="po-label">Supplier :</td>
                    <td>
                        @if ($isEditable)
                            <input class="po-editable" name="supplier_name" type="text" value="{{ $field('supplier_name') }}">
                        @else
                            <span class="po-cell-text">{{ $text($po->supplier_name) }}</span>
                        @endif
                    </td>
                    <td class="po-label">P.O. No.:</td>
                    <td>
                        @if ($isEditable)
                            <input class="po-editable" name="po_number" type="text" value="{{ $field('po_number') }}">
                        @else
                            <span class="po-cell-text">{{ $text($po->po_number) }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="po-label">Address :</td>
                    <td rowspan="2">
                        @if ($isEditable)
                            <textarea class="po-editable" name="supplier_address" rows="2" data-po-autoresize>{{ $field('supplier_address') }}</textarea>
                        @else
                            <span class="po-cell-text multiline">{{ $text($po->supplier_address) }}</span>
                        @endif
                    </td>
                    <td class="po-label">Date :</td>
                    <td>
                        @if ($isEditable)
                            <input class="po-editable" name="po_date" type="text" value="{{ $poDate }}">
                        @else
                            <span class="po-cell-text">{{ $po->po_date?->format('M d, Y') }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td class="po-label">Mode of Procurement:</td>
                    <td>
                        @if ($isEditable)
                            <input class="po-editable" name="mode_of_procurement" type="text" value="{{ $field('mode_of_procurement') }}">
                        @else
                            <span class="po-cell-text">{{ $text($po->mode_of_procurement) }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                    <td class="po-label">PR No/s.:</td>
                    <td><span class="po-cell-text">{{ $sourceDocument?->tracking_number ?? '' }}</span></td>
                </tr>
            </table>

            <table class="po-table po-gentlemen-table">
                <tr>
                    <td class="po-gentlemen-label">Gentlemen:</td>
                </tr>
                <tr>
                    <td class="po-gentlemen-text">Please furnish this office the following articles subject to the terms and conditions contained herein:</td>
                </tr>
            </table>

            <table class="po-table po-delivery-table">
                <colgroup>
                    <col style="width: 20%">
                    <col style="width: 34%">
                    <col style="width: 18%">
                    <col style="width: 28%">
                </colgroup>
                <tr>
                    <td class="po-label">Place of Delivery:</td>
                    <td>
                        @if ($isEditable)
                            <input class="po-editable" name="place_of_delivery" type="text" value="{{ old('place_of_delivery', $po->place_of_delivery ?: $po->delivery_place) }}">
                        @else
                            <span class="po-cell-text">{{ $text($po->place_of_delivery ?: $po->delivery_place) }}</span>
                        @endif
                    </td>
                    <td class="po-label">Delivery Term:</td>
                    <td>
                        @if ($isEditable)
                            <input class="po-editable" name="delivery_term" type="text" value="{{ old('delivery_term', $po->delivery_term ?: $po->delivery_terms) }}">
                        @else
                            <span class="po-cell-text">{{ $text($po->delivery_term ?: $po->delivery_terms) }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="po-label">Date of Delivery:</td>
                    <td>
                        @if ($isEditable)
                            <input class="po-editable" name="date_of_delivery" type="text" value="{{ old('date_of_delivery', $po->date_of_delivery ?: $po->delivery_date?->format('M d, Y')) }}">
                        @else
                            <span class="po-cell-text">{{ $text($po->date_of_delivery ?: $po->delivery_date?->format('M d, Y')) }}</span>
                        @endif
                    </td>
                    <td class="po-label">Payment Term:</td>
                    <td>
                        @if ($isEditable)
                            <input class="po-editable" name="payment_term" type="text" value="{{ old('payment_term', $po->payment_term ?: $po->payment_terms) }}">
                        @else
                            <span class="po-cell-text">{{ $text($po->payment_term ?: $po->payment_terms) }}</span>
                        @endif
                    </td>
                </tr>
            </table>

            <table class="po-items-table">
                <colgroup>
                    <col style="width: 7%">
                    <col style="width: 8%">
                    <col style="width: 9%">
                    <col style="width: 50%">
                    <col style="width: 13%">
                    <col style="width: 13%">
                </colgroup>
                <thead>
                    <tr>
                        <th>Item<br>No.</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Description</th>
                        <th>Unit<br>Cost</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($index = 0; $index < $rowCount; $index++)
                        @php
                            $item = $itemRows->get($index, []);
                        @endphp
                        <tr>
                            <td>
                                @if ($isEditable)
                                    <input type="hidden" name="items[{{ $index }}][source_pr_item_id]" value="{{ $item['source_pr_item_id'] ?? '' }}">
                                    <input class="po-editable po-center" name="items[{{ $index }}][item_no]" type="text" value="{{ $item['item_no'] ?? '' }}">
                                @else
                                    <span class="po-cell-text po-center">{{ $item['item_no'] ?? '' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($isEditable)
                                    <input class="po-editable po-number po-item-qty" name="items[{{ $index }}][quantity]" type="text" value="{{ $money($item['quantity'] ?? null) }}">
                                @else
                                    <span class="po-cell-text po-number">{{ filled($item['quantity'] ?? null) ? number_format((float) $item['quantity'], 2) : '' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($isEditable)
                                    <input class="po-editable po-center" name="items[{{ $index }}][unit]" type="text" value="{{ $item['unit'] ?? '' }}">
                                @else
                                    <span class="po-cell-text po-center">{{ $item['unit'] ?? '' }}</span>
                                @endif
                            </td>
                            <td class="description-cell">
                                @if ($isEditable)
                                    <textarea class="po-editable po-item-description" name="items[{{ $index }}][description]" rows="1" data-po-autoresize>{{ $item['description'] ?? '' }}</textarea>
                                @else
                                    <span class="po-cell-text multiline">{{ $item['description'] ?? '' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($isEditable)
                                    <input class="po-editable po-number po-item-unit-cost" name="items[{{ $index }}][unit_cost]" type="text" value="{{ $money($item['unit_cost'] ?? null) }}">
                                @else
                                    <span class="po-cell-text po-number">{{ filled($item['unit_cost'] ?? null) ? number_format((float) $item['unit_cost'], 2) : '' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($isEditable)
                                    <input class="po-editable po-number po-item-total" name="items[{{ $index }}][total_cost]" type="text" value="{{ $money($item['total_cost'] ?? null) }}">
                                @else
                                    <span class="po-cell-text po-number">{{ filled($item['total_cost'] ?? null) ? number_format((float) $item['total_cost'], 2) : '' }}</span>
                                @endif
                            </td>
                        </tr>
                    @endfor
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="po-total-words-cell">
                            <div>(Total Amount in Words)</div>
                            @if ($isEditable)
                                <input class="po-editable po-total-words" name="total_amount_words" type="text" value="{{ $totalAmountWords }}">
                            @else
                                <span class="po-cell-text">{{ $text($po->total_amount_words) }}</span>
                            @endif
                        </td>
                        <td class="po-total-label">Total:</td>
                        <td>
                            @if ($isEditable)
                                <input class="po-editable po-number po-grand-total" name="total_amount" type="text" value="{{ $money($totalAmount) }}">
                            @else
                                <span class="po-cell-text po-number">{{ number_format((float) $po->total_amount, 2) }}</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>

            <div class="po-penalty">
                @if ($isEditable)
                    <textarea class="po-editable" name="penalty_clause" rows="2" data-po-autoresize>{{ $penaltyClause }}</textarea>
                @else
                    <span class="po-cell-text multiline">{{ $text($po->penalty_clause, $penaltyClause) }}</span>
                @endif
            </div>

            <table class="po-table po-signature-table">
                <colgroup>
                    <col style="width: 50%">
                    <col style="width: 50%">
                </colgroup>
                <tr>
                    <td class="po-conforme-cell">
                        <p>Conforme:</p>
                        <div class="po-sign-line"></div>
                        @if ($isEditable)
                            <input class="po-editable po-sign-name" name="supplier_representative_name" type="text" value="{{ $field('supplier_representative_name') }}">
                            <input class="po-editable po-sign-title" name="supplier_representative_designation" type="text" value="{{ $field('supplier_representative_designation', 'Supplier Representative') }}">
                            <label>Date:</label>
                            <input class="po-editable po-sign-date" name="supplier_conforme_date" type="text" value="{{ $field('supplier_conforme_date') }}">
                        @else
                            <div class="po-sign-name">{{ $text($po->supplier_representative_name) }}</div>
                            <div class="po-sign-title">{{ $text($po->supplier_representative_designation, 'Supplier Representative') }}</div>
                            <div>Date: {{ $text($po->supplier_conforme_date) }}</div>
                        @endif
                    </td>
                    <td class="po-authorized-cell">
                        <p>Very truly yours,</p>
                        <div class="po-sign-line"></div>
                        @if ($isEditable)
                            <input class="po-editable po-sign-name" name="authorized_official_name" type="text" value="{{ $field('authorized_official_name') }}">
                            <input class="po-editable po-sign-title" name="authorized_official_designation" type="text" value="{{ $field('authorized_official_designation', 'Authorized Official') }}">
                        @else
                            <div class="po-sign-name">{{ $text($po->authorized_official_name) }}</div>
                            <div class="po-sign-title">{{ $text($po->authorized_official_designation, 'Authorized Official') }}</div>
                        @endif
                    </td>
                </tr>
            </table>

            <table class="po-table po-fund-table">
                <colgroup>
                    <col style="width: 58%">
                    <col style="width: 42%">
                </colgroup>
                <tr>
                    <td colspan="2">
                        @if ($isEditable)
                            <input class="po-editable po-fund-label" name="fund_available_text" type="text" value="{{ $field('fund_available_text', 'Fund Available:') }}">
                        @else
                            <span class="po-cell-text">{{ $text($po->fund_available_text, 'Fund Available:') }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="po-accountant-cell">
                        <div class="po-sign-line"></div>
                        @if ($isEditable)
                            <input class="po-editable po-sign-name" name="accountant_name" type="text" value="{{ $field('accountant_name') }}">
                            <input class="po-editable po-sign-title" name="accountant_designation" type="text" value="{{ $field('accountant_designation', 'Municipal Accountant') }}">
                        @else
                            <div class="po-sign-name">{{ $text($po->accountant_name) }}</div>
                            <div class="po-sign-title">{{ $text($po->accountant_designation, 'Municipal Accountant') }}</div>
                        @endif
                    </td>
                    <td class="po-alobs-cell">
                        <div>
                            <span>ALOBS No. :</span>
                            @if ($isEditable)
                                <input class="po-editable" name="alobs_number" type="text" value="{{ $field('alobs_number') }}">
                            @else
                                <span class="po-line-value">{{ $text($po->alobs_number) }}</span>
                            @endif
                        </div>
                        <div>
                            <span>Amount :</span>
                            @if ($isEditable)
                                <input class="po-editable po-number" name="alobs_amount" type="text" value="{{ $money($po->alobs_amount) }}">
                            @else
                                <span class="po-line-value">{{ filled($po->alobs_amount) ? number_format((float) $po->alobs_amount, 2) : '' }}</span>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>
        </article>
    </section>
</div>

@if ($isEditable)
    <script>
        function resizePoTextarea(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }

        function poNumber(value) {
            const parsed = Number(String(value || '').replace(/,/g, ''));
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function formatPoNumber(value) {
            return value > 0 ? value.toFixed(2) : '';
        }

        function recalcPoTotals() {
            let grandTotal = 0;

            document.querySelectorAll('.po-items-table tbody tr').forEach((row) => {
                const qty = poNumber(row.querySelector('.po-item-qty')?.value);
                const unitCost = poNumber(row.querySelector('.po-item-unit-cost')?.value);
                const total = row.querySelector('.po-item-total');

                if (!total) return;

                if (qty > 0 && unitCost >= 0 && !total.dataset.manual) {
                    total.value = formatPoNumber(qty * unitCost);
                }

                grandTotal += poNumber(total.value);
            });

            const grandTotalField = document.querySelector('.po-grand-total');
            if (grandTotalField && !grandTotalField.dataset.manual && grandTotal > 0) {
                grandTotalField.value = formatPoNumber(grandTotal);
            }
        }

        document.querySelectorAll('[data-po-autoresize]').forEach(resizePoTextarea);

        document.addEventListener('input', function (event) {
            if (event.target.matches('[data-po-autoresize]')) {
                resizePoTextarea(event.target);
            }

            if (event.target.matches('.po-item-total, .po-grand-total')) {
                event.target.dataset.manual = '1';
            }

            if (event.target.matches('.po-item-qty, .po-item-unit-cost, .po-item-total')) {
                recalcPoTotals();
            }
        });
    </script>
@endif
