@php
    $response = $response ?? [];
    $editable = $editable ?? false;
    $officer = $snapshot['officer_signature'] ?? null;
    $quoteLines = collect($response['items'] ?? [])->keyBy('rfq_item_id');
@endphp
<article class="rfq-response-document">
    <header class="rfq-response-document-header">
        <p>Republic of the Philippines<br>Province of Southern Leyte<br><strong>MUNICIPALITY OF TOMAS OPPUS</strong></p>
        <h2>REQUEST FOR QUOTATION</h2>
    </header>
    <div class="rfq-response-document-meta">
        <div><strong>{{ $quotation->supplier_name ?? 'Supplier / Company: ____________________' }}</strong><br>{{ $response['supplier_address'] ?? '' }}</div>
        <div>Quotation No.: <strong>{{ $snapshot['rfq_number'] }}</strong><br>Date: {{ $snapshot['rfq_date'] }}</div>
    </div>
    <p>Please quote your lowest price on the items listed below, subject to the conditions stated in this RFQ.</p>
    <div class="rfq-officer-proof">
        @if (! empty($officer['image']))<img src="{{ $officer['image'] }}" alt="Procurement Officer's electronic signature">@endif
        <strong>{{ $officer['name'] ?? $snapshot['procurement_officer_name'] ?? '' }}</strong>
        <span>Procurement Officer</span>
        @if ($officer)<small>Electronically signed {{ \Carbon\Carbon::parse($officer['signed_at'])->timezone('Asia/Manila')->format('M d, Y h:i A') }} PHT<br>{{ $officer['code'] }}</small>@endif
    </div>
    <ul class="rfq-response-terms">
        @foreach (['delivery_period', 'warranty_text', 'price_validity_text', 'philgeps_requirement_text', 'mayors_permit_requirement_text'] as $term)
            @if (filled($snapshot[$term] ?? null))<li>{{ $snapshot[$term] }}</li>@endif
        @endforeach
    </ul>
    <p><strong>ABC:</strong> PHP {{ number_format((float) ($snapshot['abc_amount'] ?? 0), 2) }}<br><strong>Purpose:</strong> {{ $snapshot['purpose'] ?? '' }}</p>
    <div class="rfq-response-table-scroll">
        <table class="rfq-response-items">
            <thead><tr><th>Item No.</th><th>Description</th><th>Qty.</th><th>Unit</th><th>Unit Price (PHP)</th><th>Total (PHP)</th></tr></thead>
            <tbody>
                @foreach ($snapshot['items'] as $index => $item)
                    <tr data-quote-item data-quantity="{{ $item['quantity'] }}">
                        <td>{{ $item['item_no'] ?: $index + 1 }}</td><td>{{ $item['description'] }}</td>
                        <td>{{ $item['quantity'] }}</td><td>{{ $item['unit_of_issue'] }}</td>
                        <td>@if ($editable)<label class="rfq-response-sr-only" for="quote-price-{{ $index }}">Unit price for {{ $item['description'] }}</label><input id="quote-price-{{ $index }}" type="number" inputmode="decimal" step="0.01" min="0" max="99999999.99" name="items[{{ $index }}][unit_price]" value="{{ old('items.'.$index.'.unit_price') }}" required data-unit-price>@else{{ isset($quoteLines[$item['id']]) ? number_format((float) $quoteLines[$item['id']]['unit_price'], 2) : '—' }}@endif</td>
                        <td data-line-total>{{ isset($quoteLines[$item['id']]) ? number_format((float) $quoteLines[$item['id']]['total'], 2) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><th colspan="5">Total Quotation (PHP)</th><th data-quote-total>{{ isset($response['total_amount']) ? number_format((float) $response['total_amount'], 2) : '—' }}</th></tr></tfoot>
        </table>
    </div>
    @if ($response)
        <p>I/We have reviewed and accepted the RFQ terms and submit the prices shown above.</p>
        <div class="rfq-response-signature-summary">
            <div>
                @if (! empty($quotation->signature_data))<img class="rfq-supplier-signature" src="{{ $quotation->signature_data }}" alt="Supplier representative's electronic signature">@endif
                <strong>{{ $response['signatory_name'] }}</strong><br>{{ $response['signatory_position'] }}<br>Printed Name &amp; Signature
            </div>
            <div><strong>Contact number:</strong> {{ $response['supplier_contact'] }}<br>
                <strong>TIN:</strong> {{ $response['supplier_tin'] }}<br>
                <strong>PhilGEPS registration:</strong> {{ $response['philgeps_registration_number'] }}<br>
                <strong>Registration type:</strong> {{ $response['philgeps_registration_type'] ?? 'Not provided' }}<br>
                <strong>Delivery period:</strong> {{ $response['delivery_days'] }} calendar days<br>
                <strong>Submitted:</strong> {{ \Carbon\Carbon::parse($response['submitted_at'])->timezone('Asia/Manila')->format('M d, Y h:i A') }} PHT
            </div>
        </div>
        @if (! empty($response['supplier_remarks']))<p><strong>Supplier remarks:</strong> {{ $response['supplier_remarks'] }}</p>@endif
        <p class="rfq-response-consent">{{ $response['consent_text'] }}</p>
    @endif
</article>
