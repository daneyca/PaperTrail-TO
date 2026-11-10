<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer"><meta name="robots" content="noindex,nofollow">
    <title>Supplier Quotation | PaperTrail</title>
    <link rel="stylesheet" href="{{ asset('css/rfq-responses.css') }}?v={{ filemtime(public_path('css/rfq-responses.css')) }}">
    <script src="{{ asset('js/rfq-response.js') }}" defer></script>
</head>
<body class="rfq-response-public">
<main class="rfq-response-shell">
    <header class="rfq-response-brand"><strong>PaperTrail</strong><span>Municipality of Tomas Oppus · Supplier Quotation</span></header>
    <section class="rfq-response-card">
        <div class="rfq-response-heading"><div><h1>{{ $quotation->supplier_name }}</h1><p>RFQ {{ $quotation->issued_snapshot['rfq_number'] }} · {{ str($quotation->status)->replace('_', ' ')->title() }}</p></div>
            <span>Response deadline: {{ $quotation->expires_at->timezone('Asia/Manila')->format('M d, Y h:i A') }} PHT</span></div>
        @if (session('status'))<p class="rfq-response-alert" role="status">{{ session('status') }}</p>@endif
        @if ($errors->any())<div class="rfq-response-errors" role="alert"><strong>Please check the following:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if ($quotation->isOpen())
            <p class="rfq-response-intro">Review the RFQ, enter your prices and supplier details, then sign and submit. Required fields are marked *. Your response is available to the staff handling this RFQ.</p>
            <form method="POST" action="{{ route('rfq-response.submit', $token) }}" data-rfq-response-form>
                @csrf
                @include('rfq-responses.document', ['snapshot' => $quotation->issued_snapshot, 'editable' => true])
                <section class="rfq-response-form-section"><h2>Supplier details</h2>
                    <div class="rfq-response-fields">
                        <label>Contact number *<input type="tel" name="supplier_contact" autocomplete="tel" maxlength="40" value="{{ old('supplier_contact') }}" required placeholder="Mobile or telephone number"></label>
                        <label>TIN *<input type="text" name="supplier_tin" inputmode="numeric" maxlength="40" value="{{ old('supplier_tin') }}" required placeholder="TIN as registered"></label>
                        <label class="rfq-response-full">Business address *<textarea name="supplier_address" maxlength="1000" autocomplete="street-address" required>{{ old('supplier_address') }}</textarea></label>
                        <label>Authorized representative's printed name *<input name="signatory_name" maxlength="255" autocomplete="name" value="{{ old('signatory_name') }}" required></label>
                        <label>Position / designation *<input name="signatory_position" maxlength="255" value="{{ old('signatory_position') }}" required></label>
                        <label>Delivery period (calendar days) *<input type="number" inputmode="numeric" name="delivery_days" min="1" max="3650" value="{{ old('delivery_days') }}" required></label>
                        <label>PhilGEPS registration number *<input name="philgeps_registration_number" maxlength="255" value="{{ old('philgeps_registration_number') }}" required></label>
                        <label>PhilGEPS registration type<input name="philgeps_registration_type" maxlength="255" value="{{ old('philgeps_registration_type') }}"></label>
                        <label class="rfq-response-full">Remarks<textarea name="supplier_remarks" maxlength="2000">{{ old('supplier_remarks') }}</textarea></label>
                    </div>
                </section>
                <section class="rfq-response-form-section"><h2>Electronic signature *</h2><p>Draw your signature using your mouse, finger, or stylus.</p>
                    <canvas class="rfq-response-signature-pad" width="900" height="240" aria-label="Draw the authorized representative's signature" data-signature-pad></canvas>
                    <input type="hidden" name="signature_data" value="{{ old('signature_data') }}" data-signature-data>
                    <button type="button" class="rfq-response-secondary" data-clear-signature>Clear signature</button>
                    <label class="rfq-response-consent-check"><input type="checkbox" name="signature_consent" value="1" required @checked(old('signature_consent'))><span>{{ \App\Services\RfqSupplierWorkflowService::CONSENT }}</span></label>
                    <p>Once submitted, this quotation is locked for staff review. Check all prices, your contact number, and your TIN before submitting.</p>
                    <button type="submit">Sign and Submit Quotation</button>
                </section>
            </form>
        @else
            <p class="rfq-response-alert">Your response is saved and locked. Status: {{ str($quotation->status)->title() }}.</p>
            @if ($quotation->status === 'rejected' && $quotation->review_remarks)<p>Review remarks: {{ $quotation->review_remarks }}</p>@endif
            @include('rfq-responses.document', ['snapshot' => $quotation->issued_snapshot, 'response' => $quotation->response_snapshot ?? []])
            <button type="button" class="no-print" onclick="window.print()">Print / Save PDF</button>
        @endif
    </section>
</main>
</body>
</html>
