@extends('layouts.dashboard')

@section('title', ($app->app_no ?? $app->app_number ?? 'Annual Procurement Plan') . ' | PaperTrail')

@section('content')
    <section class="official-app-toolbar no-print">
        <div>
            <strong>{{ $app->app_no ?? $app->app_number ?? 'APP Draft' }}</strong>
            <span>Tracking Number {{ $app->document_reference_number ?? 'Pending' }} &middot; Official Annual Procurement Plan preview</span>
        </div>
        <div class="official-app-toolbar-actions">
            <a href="{{ route('annual-procurement-plans.index') }}">Back</a>
            <a href="{{ route('annual-procurement-plans.edit', $app) }}">Edit</a>
            <a href="{{ route('annual-procurement-plans.print', $app) }}" target="_blank">Print</a>
            <x-ai.completeness-check-button
                document-type="app"
                :document-id="$app->id"
                :tracking-number="$app->displayNumber()"
                label="AI Check APP"
            />
        </div>
    </section>

    <section class="app-summary-strip no-print" aria-label="APP summary">
        <div><span>Tracking Number</span><strong>{{ $app->document_reference_number ?? 'Pending' }}</strong></div>
        <div><span>Fiscal Year</span><strong>{{ $app->fiscal_year }}</strong></div>
        <div><span>Plan Type</span><strong>{{ str($app->plan_type)->replace('_', ' ')->title() }}</strong></div>
        <div><span>Status</span><strong>{{ str($app->status)->replace('_', ' ')->title() }}</strong></div>
        <div><span>EPA Budget</span><strong>PHP {{ number_format((float) $app->total_epa_budget, 2) }}</strong></div>
        <div><span>Total Budget</span><strong>PHP {{ number_format((float) $app->total_estimated_budget, 2) }}</strong></div>
    </section>

    <section class="official-app-preview-shell">
        <div class="official-app-page">
            @include('annual-procurement-plans.partials.official-form', [
                'app' => $app,
                'categories' => $categories,
            ])
        </div>
    </section>
@endsection
