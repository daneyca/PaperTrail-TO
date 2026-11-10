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
        <div class="pr-status-page">
            <nav class="pr-status-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route($backRoute) }}">Documents</a><span aria-hidden="true">&rsaquo;</span>
                <a href="{{ route($backRoute) }}">Status Tracking</a><span aria-hidden="true">&rsaquo;</span>
                <span aria-current="page">{{ $trackingTitle }}</span>
            </nav>
            <header class="pr-status-hero">
                <span class="pr-status-hero-icon"><x-papertrail.icon name="document" /></span>
                <div><h1>PR Status Tracking</h1><p>Track the progress and routing of your Purchase Request.</p></div>
                <svg class="pr-status-hero-art" viewBox="0 0 220 110" fill="none" aria-hidden="true">
                    <path d="M26 108 44 12a6 6 0 0 1 6-5h101a7 7 0 0 1 7 8l-17 93" fill="#c2d5ff" stroke="#3971e8" stroke-width="3"/>
                    <path d="M33 108 49 17h101l-16 91" fill="#fff"/>
                    <rect x="81" y="2" width="41" height="17" rx="5" fill="#7ca7ff"/>
                    <path d="m59 36 4 4 8-8m-16 24 4 4 8-8m-16 24 4 4 8-8" stroke="#24a85a" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M80 36h47M76 56h47M72 76h35" stroke="#b3c8ed" stroke-width="5" stroke-linecap="round"/>
                    <circle cx="155" cy="63" r="27" fill="#d9f8e5" stroke="#2464dc" stroke-width="5"/><path d="m144 62 8 8 15-17" stroke="#24a85a" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="m175 83 24 24" stroke="#1654ce" stroke-width="11" stroke-linecap="round"/>
                </svg>
            </header>
            <div class="pr-status-toolbar">
                <a href="{{ route($backRoute) }}" class="pr-status-back">&larr; Back to list</a>
                <nav class="pr-status-view-switch" aria-label="Tracking view">
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'dashboard']) }}" aria-current="page">Dashboard</a>
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'hierarchy']) }}">Hierarchy</a>
                </nav>
            </div>
            @if ($pr)
                <x-documents.pr-status-dashboard :tracking="app(\App\Services\PrStatusTrackingPresenter::class)->present($pr)" />
            @else
                <div class="pr-status-panel empty-state"><strong>No source document linked yet</strong><p>The tracking dashboard will appear once a Purchase Request is linked.</p></div>
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
