@props([
    'document' => null,
    'timeline' => [],
    'currentStage' => null,
    'nextStep' => null,
])

@php
    $items = collect($timeline)->values();
@endphp

<div {{ $attributes->merge(['class' => 'document-tracking-timeline']) }}>
    @forelse ($items as $item)
        @php
            $state = $item['state'] ?? 'completed';
            $icon = $state === 'current' ? '&#9679;' : ($state === 'upcoming' ? '&#9675;' : '&#10003;');
        @endphp
        <div class="document-tracking-timeline__item is-{{ $state }}">
            <div class="document-tracking-timeline__marker" aria-hidden="true">{!! $icon !!}</div>
            <div class="document-tracking-timeline__content">
                <strong>{{ $item['label'] ?? 'Workflow Update' }}</strong>
                @if (! empty($item['date_display']))
                    <span>{{ $item['date_display'] }}</span>
                @endif
                @if (! empty($item['location']))
                    <small>{{ $item['location'] }}</small>
                @endif
            </div>
        </div>
    @empty
        <div class="document-tracking-timeline__empty">
            No routing history has been recorded yet.
        </div>
    @endforelse

    @if (filled($nextStep))
        <div class="document-tracking-timeline__item is-upcoming">
            <div class="document-tracking-timeline__marker" aria-hidden="true">&#9675;</div>
            <div class="document-tracking-timeline__content">
                <strong>Next Step</strong>
                <small>{{ $nextStep }}</small>
            </div>
        </div>
    @endif
</div>
