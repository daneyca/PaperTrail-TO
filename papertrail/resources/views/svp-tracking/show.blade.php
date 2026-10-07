@extends(request()->boolean('modal') ? 'layouts.modal' : 'layouts.dashboard')

@php
    $pr = $chain->sourcePrDocument;
    $trackingTitle = $pr?->displayNumber() ?? $chain->chain_number ?? 'SVP Chain';
@endphp

@section('title', $trackingTitle . ' Movement | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">{{ $eyebrow }}</p>
            <h1>Document Movement</h1>
            <p>Track the document hierarchy, current location, current handler, and recorded routing actions.</p>
        </div>

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
@endsection
