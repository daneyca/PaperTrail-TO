<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $po->po_number ? 'Purchase Order '.$po->po_number : 'Purchase Order' }} | PaperTrail</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/purchase-order.css') }}">
</head>
<body class="po-print-body">
    <main class="po-print-page">
        @include('bac-secretariat.purchase-orders.partials.purchase-order-excel-form', [
            'po' => $po,
            'sourceDocument' => $po->sourcePrDocument,
            'mode' => 'print',
        ])
    </main>
</body>
</html>
