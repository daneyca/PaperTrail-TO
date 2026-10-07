<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->tracking_number ?? 'PPMP Review' }} | PaperTrail</title>
    <link rel="stylesheet" href="{{ asset('css/ppmp.css') }}?v={{ filemtime(public_path('css/ppmp.css')) }}">
</head>
<body class="ppmp-print-body">
    <div class="ppmp-print-toolbar no-print">
        <a href="{{ route('bac-secretariat.ppmp.show', $document) }}">Back to Review</a>
        <button type="button" onclick="window.print()">Print PPMP</button>
        <span>Before printing, use A4 landscape and disable browser headers/footers.</span>
    </div>

    @include('head-office.ppmp._official-form', [
        'mode' => 'print',
        'document' => $document,
        'items' => $document->ppmpItems,
    ])
</body>
</html>
