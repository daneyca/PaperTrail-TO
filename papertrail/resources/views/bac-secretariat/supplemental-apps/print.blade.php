<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $supplementalApp->supplemental_app_number ?? 'Supplemental APP' }} | Print</title>
    <link rel="stylesheet" href="{{ asset('css/supplemental-app.css') }}?v={{ filemtime(public_path('css/supplemental-app.css')) }}">
</head>
<body class="supplemental-print-body">
    <div class="supplemental-print-actions no-print">
        <a href="{{ $backUrl ?? route('bac-secretariat.supplemental-apps.show', $supplementalApp) }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
        <p>Before printing, open More settings and uncheck Headers and footers.</p>
    </div>

    <main class="supplemental-print-page">
        @include('bac-secretariat.supplemental-apps.partials.official-form')
    </main>
</body>
</html>
