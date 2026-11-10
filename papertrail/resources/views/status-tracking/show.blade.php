@extends(request()->boolean('modal') ? 'layouts.modal' : 'layouts.dashboard')

@section('title', $tracking['tracking_number'].' Movement | PaperTrail')

@push('vendor-styles')
    <link rel="stylesheet" href="{{ asset('css/pr-status-tracking.css') }}?v={{ filemtime(public_path('css/pr-status-tracking.css')) }}">
@endpush

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div><p class="eyebrow">{{ $eyebrow }}</p><h1>Document Movement</h1><p>Track document progress, routing activity, and the next action.</p></div>
        <a href="{{ route('status-tracking.index') }}" class="dashboard-action secondary-action">Back to List</a>
    </section>
    <div class="pr-status-page">
        @if ($canShowHierarchy)
            <nav class="document-filter-tabs" aria-label="Tracking view">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'dashboard']) }}" class="{{ request('view') !== 'hierarchy' ? 'is-active' : '' }}" @if (request('view') !== 'hierarchy') aria-current="page" @endif>Dashboard</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'hierarchy']) }}" class="{{ request('view') === 'hierarchy' ? 'is-active' : '' }}" @if (request('view') === 'hierarchy') aria-current="page" @endif>Hierarchy</a>
            </nav>
        @endif
        @if ($canShowHierarchy && request('view') === 'hierarchy')
            <section class="table-panel"><div class="section-heading"><div><p class="eyebrow">Routing History</p><h2>{{ $tracking['tracking_number'] }}</h2></div></div><x-documents.movement-timeline :document="$document" /></section>
        @elseif ($isPr)
            <x-documents.pr-status-dashboard :tracking="app(\App\Services\PrStatusTrackingPresenter::class)->present($document)" />
        @else
            <section class="table-panel pr-status-panel">
                <div class="section-heading"><div><p class="eyebrow">{{ $tracking['document_type'] }}</p><h2>{{ $tracking['tracking_number'] }}</h2><p>{{ $tracking['title'] }}</p></div></div>
                <div class="pr-status-summary">
                    <div><span class="pr-status-label">Current status</span><strong>{{ $tracking['current_status'] }}</strong></div>
                    <div><span class="pr-status-label">Current location</span><strong>{{ $tracking['current_holder'] }}</strong></div>
                    <div><span class="pr-status-label">Current handler</span><strong>{{ $tracking['current_handler'] ?? 'Not assigned' }}</strong></div>
                    <div><span class="pr-status-label">Current stage</span><strong>{{ $tracking['current_stage'] }}</strong></div>
                </div>
            </section>
            <div class="pr-status-columns">
                <section class="table-panel pr-status-panel">
                    <header class="section-heading"><div><p class="eyebrow">Routing History</p><h2>Activity Timeline</h2></div></header>
                    <ol class="pr-status-events">
                        @forelse ($tracking['timeline'] as $event)
                            <li class="pr-status-event is-{{ $event['state'] ?? 'completed' }}"><span class="pr-status-event-marker" aria-hidden="true">@if (($event['state'] ?? '') === 'completed')<x-papertrail.icon name="check" />@endif</span><div class="pr-status-event-card pr-status-upcoming"><div><strong>{{ $event['label'] }}</strong><p>{{ $event['date_display'] ?? 'Time not recorded' }}@if ($event['location'] ?? null) &middot; {{ $event['location'] }}@endif</p>@if ($event['actor'] ?? null)<p>By: {{ $event['actor'] }}</p>@endif @if ($event['stage'] ?? null)<p>{{ $event['stage'] }}</p>@endif @if ($event['comments'] ?? null)<p>{{ $event['comments'] }}</p>@endif</div></div></li>
                        @empty
                            <li class="pr-status-no-history">No routing history has been recorded yet.</li>
                        @endforelse
                    </ol>
                </section>
                <section class="table-panel pr-status-next pr-status-panel">
                    <header class="section-heading"><div><p class="eyebrow">For Action</p><h2>Next Action</h2></div></header>
                    <div class="pr-status-next-body"><p>{{ $tracking['next_step'] }}</p>@if ($tracking['current_handler'] ?? null)<div class="pr-status-next-person">Current handler: <strong>{{ $tracking['current_handler'] }}</strong></div>@endif</div>
                </section>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    @if ($isPr && request('view') !== 'hierarchy')<script src="{{ asset('js/pr-status-tracking.js') }}?v={{ filemtime(public_path('js/pr-status-tracking.js')) }}" defer></script>@endif
@endpush
