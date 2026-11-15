@php
    $response = $response ?? [];
    $editable = $editable ?? false;
    $officer = $snapshot['officer_signature'] ?? null;
    $quoteLines = collect($response['items'] ?? [])->keyBy('rfq_item_id');
    $items = collect($snapshot['items'] ?? []);
    $rowCount = match (true) {
        $items->count() <= 8 => 18,
        $items->count() <= 14 => 20,
        $items->count() <= 20 => 24,
        default => $items->count() + 4,
    };
    $terms = [
        'delivery_period' => 'Delivery period within __________ calendar days.',
        'warranty_text' => 'Warranty shall be a period of six (6) months for supplies and materials. One (1) year for equipment from Date of acceptance.',
        'price_validity_text' => 'Price validity shall be for a period of sixty (60) calendar days.',
        'philgeps_requirement_text' => 'PhilGeps Registration Certificate Number shall be provided in the quotation.',
        'mayors_permit_requirement_text' => 'Mayors Business Permit shall be attached upon submission of the quotation.',
    ];
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp
<div class="rfq-response-document-wrap">
<div class="rfq-response-paper">
<article class="rfq-response-document">
    <header class="rfq-response-document-header">
        @include('rfqs.partials.official-header')
        <h2>REQUEST FOR QUOTATION</h2>
    </header>
    <div class="rfq-response-controls">
        <div><span>Date:</span><span>{{ ($snapshot['rfq_date'] ?? null) ?: 'Pending Procurement Controller' }}</span></div>
        <div><span>Quotation No.:</span><span>{{ ($snapshot['rfq_number'] ?? null) ?: 'Pending Procurement Controller' }}</span></div>
    </div>
    <div class="rfq-response-supplier">
        <div>{{ $response['supplier_name'] ?? $quotation->supplier_name ?? '' }}</div>
        <div>{{ $response['supplier_address'] ?? '' }}</div>
    </div>
    <p class="rfq-response-instruction">Please quote your lowest price on the item listed below, subject to the General Conditions stated herein under, stating the shortest time of delivery and submit your quotation duly signed by your representative not later than ____________, in the return envelope attached herewith.</p>
    <div class="rfq-officer-proof">
        <div class="rfq-officer-signature">
            @if (! empty($officer['image']))<img src="{{ $officer['image'] }}" alt="Procurement Officer's electronic signature">@endif
            <strong>{{ $officer['name'] ?? $snapshot['procurement_officer_name'] ?? '' }}</strong>
        </div>
        <span>{{ ($snapshot['procurement_officer_designation'] ?? null) ?: 'Procurement Officer' }}</span>
    </div>
    <div class="rfq-response-terms">
        <strong>TERM</strong>
        <ol>
            @foreach ($terms as $field => $default)
                <li>{{ filled($snapshot[$field] ?? null) ? $snapshot[$field] : $default }}</li>
            @endforeach
        </ol>
    </div>
    <div class="rfq-response-purpose">
        <div><strong>ABC:</strong><span>Php {{ number_format((float) ($snapshot['abc_amount'] ?? 0), 2) }}</span></div>
        <div><strong>Purpose:</strong><span>{{ $snapshot['purpose'] ?? '' }}</span></div>
    </div>
    <table class="rfq-response-items">
        <colgroup><col style="width:10%"><col style="width:40%"><col style="width:10%"><col style="width:14%"><col style="width:13%"><col style="width:13%"></colgroup>
        <thead><tr><th>ITEM NO.</th><th>DESCRIPTION</th><th>QTY</th><th>UNIT OF<br>ISSUE</th><th>UNIT PRICE</th><th>TOTAL</th></tr></thead>
        <tbody>
            @foreach ($items as $index => $item)
                @php $line = $quoteLines->get($item['id']); @endphp
                <tr data-quote-item data-quantity="{{ $item['quantity'] }}">
                    <td>{{ $item['item_no'] ?: $index + 1 }}</td><td>{{ $item['description'] }}</td>
                    <td>{{ $quantity($item['quantity']) }}</td><td>{{ $item['unit_of_issue'] }}</td>
                    <td>@if ($editable)<label class="rfq-response-sr-only" for="quote-price-{{ $index }}">Unit price for {{ $item['description'] }}</label><input id="quote-price-{{ $index }}" type="number" inputmode="decimal" step="0.01" min="0" max="99999999.99" name="items[{{ $index }}][unit_price]" value="{{ old('items.'.$index.'.unit_price') }}" required data-unit-price>@elseif ($line){{ number_format((float) $line['unit_price'], 2) }}@endif</td>
                    <td data-line-total>@if ($line){{ number_format((float) $line['total'], 2) }}@endif</td>
                </tr>
            @endforeach
            @for ($index = $items->count(); $index < $rowCount; $index++)
                <tr class="rfq-response-blank-row" aria-hidden="true"><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            @endfor
        </tbody>
        @if ($editable || $response)
            <tfoot><tr><th colspan="5">TOTAL QUOTATION (PHP)</th><td data-quote-total>{{ isset($response['total_amount']) ? number_format((float) $response['total_amount'], 2) : '' }}</td></tr></tfoot>
        @endif
    </table>
    <footer class="rfq-response-footer">
        <p>After having carefully read and accepted your General Condition Below, I/We quote you on items and prices noted above.</p>
        <div class="rfq-response-signature-grid">
            <div>
                <div class="rfq-response-signature-value">
                    @if (! empty($quotation->signature_data))<img class="rfq-supplier-signature" src="{{ $quotation->signature_data }}" alt="Supplier representative's electronic signature">@endif
                    {{ $response['signatory_name'] ?? '' }}
                </div>
                <div>Printed Name &amp; Signature</div>
                <div class="rfq-response-signature-value">{{ $response['supplier_contact'] ?? '' }}</div>
                <div>Tel No. / Cellphone No.</div>
                <div class="rfq-response-signature-value">{{ $response['supplier_tin'] ?? '' }}</div>
                <div>TIN No.</div>
            </div>
            <div>
                <div class="rfq-response-signature-value">{{ $response['philgeps_registration_type'] ?? '' }}</div>
                <div>PhilGeps Type of Registration</div>
                <div class="rfq-response-signature-value">{{ $response['philgeps_registration_number'] ?? '' }}</div>
                <div>PhilGeps Registration Number</div>
                <div class="rfq-response-signature-value">{{ ! empty($response['submitted_at']) ? \Carbon\Carbon::parse($response['submitted_at'])->timezone('Asia/Manila')->format('m/d/Y') : '' }}</div>
                <div>Date</div>
            </div>
        </div>
    </footer>
    @if ($officer || $response)
        <div class="rfq-response-audit no-print">
            @if ($officer)<p>Electronically signed {{ \Carbon\Carbon::parse($officer['signed_at'])->timezone('Asia/Manila')->format('M d, Y h:i A') }} PHT &middot; {{ $officer['code'] }}</p>@endif
            @if ($response)
                <p>{{ $response['signatory_position'] ?? '' }} &middot; Delivery period: {{ $response['delivery_days'] }} calendar days &middot; Submitted: {{ \Carbon\Carbon::parse($response['submitted_at'])->timezone('Asia/Manila')->format('M d, Y h:i A') }} PHT</p>
                @if (! empty($response['supplier_remarks']))<p><strong>Supplier remarks:</strong> {{ $response['supplier_remarks'] }}</p>@endif
                <p>{{ $response['consent_text'] ?? '' }}</p>
            @endif
        </div>
    @endif
</article>
</div>
</div>
