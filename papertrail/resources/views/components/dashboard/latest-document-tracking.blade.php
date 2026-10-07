@props([
    'document' => null,
    'steps' => [],
    'history' => [],
])

@php
    $items = collect($history)->take(4)->values();
    $trackingNumber = $document['number'] ?? null;
    $documentTitle = $document['title'] ?? 'Latest procurement document';
    $documentType = $document['typeLabel'] ?? $document['type'] ?? 'Procurement Document';
    $status = $document['status'] ?? 'No status yet';
    $statusTone = $document['statusTone'] ?? 'pending';
    $currentOffice = $document['currentOffice'] ?? $document['currentHolder'] ?? 'Not routed';
    $updatedAt = $document['updatedAt'] ?? $document['submittedAt'] ?? null;
    $viewUrl = $document['viewUrl'] ?? $document['url'] ?? null;
@endphp

<section {{ $attributes->class(['dashboard-tracking-card']) }} aria-label="Latest document tracking">
    <div class="dashboard-tracking-card__header">
        <div>
            <p class="eyebrow">Latest Document Tracking</p>
            <h2>Track current document movement</h2>
            <span>Parcel-style view of the newest procurement record visible to your account.</span>
        </div>

        @if ($viewUrl)
            <a class="dashboard-tracking-card__view" href="{{ $viewUrl }}">
                <x-papertrail.icon name="view" />
                View
            </a>
        @endif
    </div>

    @if ($document)
        <div class="dashboard-tracking-summary">
            <div class="dashboard-tracking-summary__main">
                <span class="dashboard-tracking-summary__type">{{ $documentType }}</span>
                <strong>{{ $trackingNumber ?? 'Draft document' }}</strong>
                <small>{{ \Illuminate\Support\Str::limit($documentTitle, 72) }}</small>
            </div>

            <div class="dashboard-tracking-summary__meta">
                <span class="procurement-status-badge status-{{ $statusTone }}">{{ $status }}</span>
                <small>Current location</small>
                <strong>{{ $currentOffice }}</strong>
            </div>
        </div>

        <div class="dashboard-parcel-progress" style="--dashboard-timeline-steps: {{ max(1, count($steps)) }}" aria-label="Document progress">
            @foreach ($steps as $step)
                @php
                    $state = $step['state'] ?? 'upcoming';
                    $stepIcon = $step['icon'] ?? 'document';
                    $stepLabel = (string) ($step['label'] ?? 'Step');
                    $stepId = 'compact-timeline-' . \Illuminate\Support\Str::slug($stepLabel);
                @endphp
                <div id="{{ $stepId }}" class="dashboard-parcel-progress__stage is-{{ $state }}">
                    <div class="dashboard-parcel-progress__dot">
                        <x-papertrail.icon :name="$stepIcon" />
                    </div>
                    <span>{{ $stepLabel }}</span>
                </div>
            @endforeach
        </div>

        <div class="dashboard-tracking-history">
            <div class="dashboard-tracking-history__title">
                <strong>Activity history</strong>
                @if ($updatedAt)
                    <span>Updated {{ $updatedAt }}</span>
                @endif
            </div>

            @forelse ($items as $item)
                <div class="dashboard-tracking-history__item">
                    <span class="dashboard-tracking-history__marker" aria-hidden="true"></span>
                    <div>
                        <strong>{{ $item['label'] ?? 'Workflow Updated' }}</strong>
                        <span>{{ $item['date'] ?? 'No timestamp' }}</span>
                        <small>{{ $item['location'] ?? 'PaperTrail' }}{{ ! empty($item['actor']) ? ' by ' . $item['actor'] : '' }}</small>
                    </div>
                </div>
            @empty
                <div class="dashboard-tracking-history__empty">
                    No movement history yet. The first route update will appear here.
                </div>
            @endforelse
        </div>
    @else
        <div class="dashboard-tracking-empty">
            <strong>No document to track yet</strong>
            <span>Create or receive a procurement document, then the latest movement will appear here.</span>
        </div>
    @endif
</section>
