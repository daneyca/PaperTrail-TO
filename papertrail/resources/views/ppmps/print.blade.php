<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $ppmp->displayNumber() }} | PaperTrail</title>
    <link rel="stylesheet" href="{{ asset('css/ppmp.css') }}?v={{ filemtime(public_path('css/ppmp.css')) }}">
</head>
<body class="ppmp-print-body">
    <div class="ppmp-print-toolbar no-print">
        <a href="{{ route('ppmps.show', $ppmp) }}">Back to Preview</a>
        <button type="button" onclick="window.print()">Print PPMP</button>
        <span>Before printing, set paper to A4 landscape and disable browser headers/footers.</span>
    </div>

    @include('ppmps.partials.official-form', ['ppmp' => $ppmp, 'items' => $ppmp->items, 'mode' => 'print'])
</body>
</html>
