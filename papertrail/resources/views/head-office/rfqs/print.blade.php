<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $rfq->rfq_number ? 'RFQ '.$rfq->rfq_number : 'RFQ' }} | PaperTrail</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rfq.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rfq-responses.css') }}">
</head>
<body class="rfq-print-body">
    <main class="rfq-print-page">
        @if ($rfq->supplier_response_enabled && app(\App\Services\RfqSupplierWorkflowService::class)->officerSignature($rfq))
            @include('rfq-responses.document', ['snapshot' => app(\App\Services\RfqSupplierWorkflowService::class)->issuedSnapshot($rfq)])
        @else
        @include('head-office.rfqs.partials.rfq-excel-form', [
            'rfq' => $rfq,
            'sourceDocument' => $rfq->sourcePrDocument,
            'sourceResolution' => $rfq->sourceBacResolution,
            'mode' => 'print',
        ])
        @endif
    </main>
    <script>window.addEventListener('load', () => window.prepareRfqPrint?.());</script>
</body>
</html>
