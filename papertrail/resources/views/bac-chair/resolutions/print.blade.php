<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $resolution->resolution_number ? 'BAC Resolution '.$resolution->resolution_number : 'BAC Resolution' }} | PaperTrail</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/bac-resolution.css') }}">
    <link rel="stylesheet" href="{{ asset('css/e-signature.css') }}">
</head>
<body class="resolution-print-body">
    <div class="resolution-print-toolbar no-print">
        <p>Before printing, open More settings and uncheck Headers and footers.</p>
        <button type="button" onclick="printResolutionDocument()">Print BAC Resolution</button>
        <a href="{{ route('bac-chair.resolutions.show', $resolution) }}">Back</a>
    </div>

    <main class="resolution-print-page">
        @include('bac-secretariat.resolutions.partials.resolution-word-editor', [
            'resolution' => $resolution,
            'sourceDocument' => $sourceDocument,
            'mode' => 'print',
            'signatureSlots' => $signatureSlots ?? collect(),
            'bacChairSignature' => $signedSignature,
        ])
    </main>
</body>
</html>
