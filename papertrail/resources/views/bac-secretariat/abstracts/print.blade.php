<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $abstract->abstract_number ? 'Abstract '.$abstract->abstract_number : 'Abstract' }} | PaperTrail</title>
    <link rel="stylesheet" href="{{ asset('css/abstract.css') }}">
</head>
<body class="abstract-print-body">
    <main class="abstract-print-page">
        @include('bac-secretariat.abstracts.partials.abstract-excel-form', [
            'abstract' => $abstract,
            'sourceDocument' => $abstract->sourcePrDocument,
            'sourceRfq' => $abstract->sourceRfq,
            'sourceResolution' => $abstract->sourceBacResolution,
            'mode' => 'print',
        ])
    </main>
    <script>window.addEventListener('load', () => window.prepareAbstractPrint?.());</script>
</body>
</html>
