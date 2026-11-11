<section style="padding:24px;border-bottom:1px solid #e5edf6" aria-label="Purchase Order for acceptance">
    <h2>Purchase Order {{ $order['po_number'] }}</h2>
    <p>Date: {{ $order['po_date'] }} · Supplier: {{ $order['supplier_name'] }}</p>
    <p>{{ $order['supplier_address'] }}</p>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;text-align:left">
            <thead><tr><th>Item</th><th>Description</th><th>Quantity</th><th>Unit</th><th>Unit price (PHP)</th><th>Total (PHP)</th></tr></thead>
            <tbody>
                @foreach ($order['items'] as $item)
                    <tr>
                        <td>{{ $item['item_no'] }}</td><td>{{ $item['description'] ?: $item['item_description'] }}</td>
                        <td>{{ $item['quantity'] }}</td><td>{{ $item['unit'] }}</td>
                        <td>{{ number_format((float) $item['unit_cost'], 2) }}</td><td>{{ number_format((float) $item['total_cost'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p><strong>Total: PHP {{ number_format((float) $order['total_amount'], 2) }}</strong></p>
    @foreach (['place_of_delivery' => 'Place of delivery', 'date_of_delivery' => 'Delivery date', 'delivery_term' => 'Delivery terms', 'payment_term' => 'Payment terms', 'penalty_clause' => 'Terms'] as $key => $label)
        @if (filled($order[$key] ?? null))<p><strong>{{ $label }}:</strong> {{ $order[$key] }}</p>@endif
    @endforeach
</section>
