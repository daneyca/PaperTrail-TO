@extends(request()->boolean('modal') ? 'layouts.modal' : 'layouts.dashboard')

@php
    $pr = $chain->sourcePrDocument;
    $trackingTitle = $pr?->displayNumber() ?? $chain->chain_number ?? 'SVP Chain';
    $hierarchyView = request()->query('view') === 'hierarchy';
@endphp

@section('title', $trackingTitle . ' Movement | PaperTrail')

@push('vendor-styles')
    <link rel="stylesheet" href="{{ asset('css/pr-status-tracking.css') }}?v={{ filemtime(public_path('css/pr-status-tracking.css')) }}">
@endpush

@section('content')
    @if (! $hierarchyView)
        <section class="dashboard-hero admin-users-hero">
            <div>
                <p class="eyebrow">{{ $eyebrow }}</p>
                <h1>Document Movement</h1>
                <p>Track Purchase Request progress, routing activity, and the next action.</p>
            </div>
            <a href="{{ route($backRoute) }}" class="dashboard-action secondary-action">Back to List</a>
        </section>

        <div class="pr-status-page">
            <div class="pr-status-toolbar">
                <nav class="document-filter-tabs" aria-label="Tracking view">
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'dashboard']) }}" class="is-active" aria-current="page">Dashboard</a>
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'hierarchy']) }}">Hierarchy</a>
                </nav>
            </div>
            @if ($pr)
                <x-documents.pr-status-dashboard :tracking="app(\App\Services\PrStatusTrackingPresenter::class)->present($pr)" />
            @else
                <div class="table-panel empty-state"><strong>No source document linked yet</strong><p>The tracking dashboard will appear once a Purchase Request is linked.</p></div>
            @endif
        </div>
    @else
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">{{ $eyebrow }}</p>
            <h1>Document Movement</h1>
            <p>Track the document hierarchy, current location, current handler, and recorded routing actions.</p>
        </div>

        <a href="{{ request()->fullUrlWithQuery(['view' => 'dashboard']) }}" class="dashboard-action secondary-action">View Dashboard</a>
        <a href="{{ route($backRoute) }}" class="dashboard-action secondary-action">Back to List</a>
    </section>

    <section class="table-panel svp-movement-page" aria-label="Document movement hierarchy">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Routing History</p>
                <h2>{{ $trackingTitle }}</h2>
            </div>
        </div>

        @if ($pr)
            <x-documents.movement-timeline :document="$pr" class="document-movement--status-tracking" />
        @else
            <div class="empty-state">
                <strong>No source document linked yet</strong>
                <p>The movement hierarchy will appear once this chain has a source Purchase Request document.</p>
            </div>
        @endif
    </section>
    @endif
@endsection

@push('scripts')
    @unless ($hierarchyView)
        <script src="{{ asset('js/pr-status-tracking.js') }}?v={{ filemtime(public_path('js/pr-status-tracking.js')) }}" defer></script>
    @endunless
@endpush
