<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inspection and Acceptance Report {{ $record->document_reference_number }} | PaperTrail</title>
    <link rel="stylesheet" href="{{ asset('css/inspection-acceptance.css') }}?v={{ filemtime(public_path('css/inspection-acceptance.css')) }}">
</head>
<body class="iar-print-body">
    <nav class="iar-print-toolbar">
        <a href="{{ $purchaseOrder->exists ? route('head-office.inspection.show', $purchaseOrder) : route('head-office.inspection.index') }}">Back to Inspection / Acceptance</a>
        <button type="button" onclick="window.print()">Print Report</button>
    </nav>
    <main>@include('head-office.inspection.partials.report')</main>
</body>
</html>
