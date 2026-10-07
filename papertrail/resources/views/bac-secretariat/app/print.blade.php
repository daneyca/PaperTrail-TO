<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $app->app_number ?? 'Annual Procurement Plan' }} | PaperTrail</title>
    <link rel="stylesheet" href="{{ asset('css/app-form.css') }}?v={{ filemtime(public_path('css/app-form.css')) }}">
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
    </style>
</head>
<body class="app-print-body">
    <main class="app-print-page">
        <div class="app-toolbar no-print">
            <div>
                <strong>{{ $app->app_number ?? 'Annual Procurement Plan' }}</strong>
                <span>For best result, use Paper size: A4 and Layout: Landscape. Disable browser headers and footers.</span>
            </div>
            <a href="{{ route('bac-secretariat.app.show', $app) }}">Back</a>
            <button type="button" onclick="printAppDocument()">Print</button>
        </div>

        @include('bac-secretariat.app.partials.app-landscape-form', [
            'app' => $app,
            'mode' => 'print',
            'signatureSlots' => $signatureSlots ?? collect(),
        ])
    </main>
    <script>window.addEventListener('load', () => window.prepareAppPrint?.());</script>
</body>
</html>
