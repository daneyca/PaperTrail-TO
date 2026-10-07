<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $app->app_no ?? $app->app_number ?? 'Annual Procurement Plan' }} | Print</title>
    <link rel="stylesheet" href="{{ asset('css/annual-procurement-plan.css') }}?v={{ filemtime(public_path('css/annual-procurement-plan.css')) }}">
</head>
<body class="official-app-print-body">
    <div class="official-app-toolbar no-print">
        <div>
            <strong>Print Annual Procurement Plan</strong>
            <span>Before printing, open More settings and uncheck Headers and footers.</span>
        </div>
        <div class="official-app-toolbar-actions">
            <a href="{{ route('annual-procurement-plans.show', $app) }}">Back</a>
            <button type="button" onclick="window.print()">Print</button>
        </div>
    </div>

    <main class="official-app-page">
        @include('annual-procurement-plans.partials.official-form', [
            'app' => $app,
            'categories' => $categories,
        ])
    </main>
</body>
</html>
