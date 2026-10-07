@extends('layouts.dashboard')

@section('title', ($supplementalApp->supplemental_app_number ?? 'Supplemental APP') . ' | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">BAC Secretariat</p>
            <h1>{{ $supplementalApp->supplemental_app_number ?? 'Supplemental APP Draft' }}</h1>
            <p>{{ $supplementalApp->title ?? 'Supplemental Annual Procurement Plan' }}</p>
        </div>

        <div class="hero-actions">
            <span class="status-pill status-{{ $supplementalApp->status }}">{{ str($supplementalApp->status)->replace('_', ' ')->title() }}</span>
            <a href="{{ route('bac-secretariat.supplemental-apps.index') }}" class="dashboard-action secondary-action">Back to List</a>
        </div>
    </section>

    <section class="pr-action-toolbar no-print">
        <div class="pr-toolbar-group">
            @if ($supplementalApp->isEditable())
                <a href="{{ route('bac-secretariat.supplemental-apps.edit', $supplementalApp) }}" class="btn-pr-primary">Edit</a>
            @endif
            <a href="{{ route('bac-secretariat.supplemental-apps.print', $supplementalApp) }}" target="_blank" class="btn-pr-secondary">Print</a>
            <x-ai.completeness-check-button
                document-type="supplemental_app"
                :document-id="$supplementalApp->id"
                :tracking-number="$supplementalApp->supplemental_app_number"
                label="AI Check Supplemental APP"
            />
        </div>

        <div class="pr-toolbar-group">
            @if (in_array($supplementalApp->status, ['draft', 'created'], true))
                <form method="POST" action="{{ route('bac-secretariat.supplemental-apps.submit', $supplementalApp) }}" onsubmit="return confirm('Submit this Supplemental APP?');">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn-pr-secondary">Submit</button>
                </form>
            @endif

            @if (in_array($supplementalApp->status, ['draft', 'created', 'submitted'], true))
                <form method="POST" action="{{ route('bac-secretariat.supplemental-apps.accept', $supplementalApp) }}" onsubmit="return confirm('Accept this Supplemental APP and mark the PR ready for BAC Resolution?');">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn-pr-primary">Accept</button>
                </form>
            @endif
        </div>
    </section>

    <section class="supplemental-official-preview-card">
        @include('bac-secretariat.supplemental-apps.partials.official-form')
    </section>

    @if ($supplementalApp->sourcePrDocument)
        <section class="dashboard-widget widget-wide">
            <div class="widget-heading">
                <div>
                    <p class="eyebrow">Linked PR</p>
                    <h2>Purchase Request Reference</h2>
                </div>
                <div class="table-actions">
                    <a href="{{ route('bac-secretariat.pr.show', $supplementalApp->sourcePrDocument) }}">View PR</a>
                </div>
            </div>
            <div class="my-document-summary-list supplemental-summary-list">
                <div><span>Tracking Number</span><strong>{{ $supplementalApp->sourcePrDocument->tracking_number }}</strong></div>
                <div><span>PR No.</span><strong>{{ $supplementalApp->sourcePrDocument->pr_no ?? 'N/A' }}</strong></div>
                <div><span>Status</span><strong>{{ str($supplementalApp->sourcePrDocument->status)->replace('_', ' ')->title() }}</strong></div>
                <div><span>Total Amount</span><strong>PHP {{ number_format((float) $supplementalApp->sourcePrDocument->total_amount, 2) }}</strong></div>
            </div>
        </section>
    @endif

    <x-documents.attachments-panel
        :document="$supplementalApp"
        document-type="supplemental_app"
        :can-upload="$supplementalApp->isEditable()"
        title="Supplemental APP Supporting Documents"
    />

    @if ($supplementalApp->sourcePrDocument)
        <section class="dashboard-widget widget-wide">
            <div class="widget-heading">
                <div>
                    <p class="eyebrow">Routing History</p>
                    <h2>Linked PR Movement</h2>
                </div>
            </div>
            <ol class="routing-timeline">
                @forelse ($supplementalApp->sourcePrDocument->routingHistories as $history)
                    <li>
                        <strong>{{ $history->action }}</strong>
                        <span>{{ $history->action_at?->format('M d, Y h:i A') }} by {{ $history->actionBy?->name ?? 'System' }}</span>
                        <p>{{ $history->comments ?? 'No comments recorded.' }}</p>
                    </li>
                @empty
                    <li><strong>No routing history yet</strong><span>Linked PR workflow movement will appear here.</span></li>
                @endforelse
            </ol>
        </section>
    @endif
@endsection
