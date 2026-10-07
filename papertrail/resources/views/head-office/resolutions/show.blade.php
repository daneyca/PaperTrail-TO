@extends('layouts.dashboard')

@section('title', ($resolution->resolution_number ?: 'BAC Resolution') . ' | PaperTrail')

@section('content')
    @php
        $nextStep = $nextStep ?? [];
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Received BAC Resolution</p>
            <h1>{{ $resolution->resolution_number ? 'BAC Resolution No. '.$resolution->resolution_number : 'BAC Resolution' }}</h1>
            <p>Review the returned BAC Resolution for {{ $resolution->sourcePrDocument?->submittingOffice?->name ?? 'your office' }}.</p>
        </div>
        <a href="{{ route('head-office.resolutions.index') }}" class="dashboard-action secondary-action">Back to Received Resolutions</a>
    </section>

    <section class="bac-pr-summary-strip" aria-label="BAC Resolution summary">
        <div><span>Tracking Number</span><strong>{{ $resolution->document_reference_number ?? 'Pending' }}</strong></div>
        <div><span>Source PR</span><strong>{{ $resolution->sourcePrDocument?->pr_no ?? $resolution->sourcePrDocument?->tracking_number ?? 'N/A' }}</strong></div>
        <div><span>Status</span><strong>{{ str($resolution->status)->replace('_', ' ')->title() }}</strong></div>
        <div><span>Next Step</span><strong>{{ $nextStep['label'] ?? $resolution->sourcePrDocument?->stage ?? 'N/A' }}</strong></div>
        <div><span>Total Amount</span><strong>PHP {{ number_format((float) ($resolution->total_amount ?? $resolution->abc_amount), 2) }}</strong></div>
        <div><span>Returned Date</span><strong>{{ $resolution->returned_at?->format('M d, Y') ?? 'N/A' }}</strong></div>
    </section>

    <section class="resolution-page-toolbar no-print">
        <div>
            <strong>{{ $resolution->project_title ?? $resolution->title ?? 'BAC Resolution' }}</strong>
            <span>{{ $canAcknowledge ? ($nextStep['description'] ?? 'Ready for acknowledgment') : ($nextStep['description'] ?? 'Read-only') }}</span>
        </div>
        <a href="{{ route('head-office.resolutions.index') }}">Back</a>
        <x-ai.completeness-check-button
            document-type="bac_resolution"
            :document-id="$resolution->id"
            :tracking-number="$resolution->resolution_number"
        />
        @if ($canAcknowledge)
            <form method="POST" action="{{ route('head-office.resolutions.acknowledge', $resolution) }}" onsubmit='return confirm(@json($nextStep["confirm"] ?? "Acknowledge this BAC Resolution and continue the SVP process?"));'>
                @csrf
                @method('PATCH')
                <button type="submit">{{ $nextStep['button_label'] ?? 'Acknowledge / Continue' }}</button>
            </form>
        @endif
    </section>

    @include('bac-secretariat.resolutions.partials.resolution-word-editor', [
        'resolution' => $resolution,
        'sourceDocument' => $resolution->sourcePrDocument,
        'mode' => 'show',
        'signatureSlots' => $signatureSlots ?? collect(),
        'bacChairSignature' => $signedSignature ?? null,
    ])

    <section class="dashboard-widget widget-wide no-print">
        <div class="widget-heading">
            <div>
                <p class="eyebrow">Routing History</p>
                <h2>Source PR Movement</h2>
            </div>
        </div>

        <ol class="routing-timeline bac-pr-routing-timeline">
            @forelse ($resolution->sourcePrDocument?->routingHistories ?? [] as $history)
                <li>
                    <strong>{{ $history->action }}</strong>
                    <span>{{ $history->action_at?->format('M d, Y h:i A') }} by {{ $history->actionBy?->name ?? 'System' }}</span>
                    <p>{{ $history->fromOffice?->name ?? 'N/A' }} to {{ $history->toOffice?->name ?? 'N/A' }} &middot; {{ str($history->status_from ?? 'new')->replace('_', ' ')->title() }} to {{ str($history->status_to)->replace('_', ' ')->title() }}</p>
                    @if ($history->comments)<p>{{ $history->comments }}</p>@endif
                </li>
            @empty
                <li><strong>No routing history yet</strong><span>Routing entries will appear after workflow actions.</span></li>
            @endforelse
        </ol>
    </section>
@endsection
