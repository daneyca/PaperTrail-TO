@props([
    'timeline' => [],
    'nextStep' => null,
])

@php
    $items = collect($timeline ?? [])->values();

    $historyState = function ($state): string {
        $state = strtolower((string) ($state ?? 'completed'));

        return in_array($state, ['completed', 'current', 'pending', 'upcoming'], true) ? $state : 'completed';
    };
@endphp

<div {{ $attributes->merge(['class' => 'tracking-history']) }}>
    <div class="tracking-history__heading">Activity History</div>

    @forelse ($items as $item)
        @php
            $state = $historyState($item['state'] ?? 'completed');
            $icon = $state === 'current' ? '&#9679;' : ($state === 'pending' || $state === 'upcoming' ? '&#9675;' : '&#10003;');
        @endphp

        <div class="history-item is-{{ $state }}">
            <div class="history-marker" aria-hidden="true">{!! $icon !!}</div>
            <div class="history-content">
                <h4>{{ $item['label'] ?? 'Workflow Update' }}</h4>
                @if (! empty($item['date_display']))
                    <p>{{ $item['date_display'] }}</p>
                @endif
                @if (! empty($item['location']))
                    <p>{{ $item['location'] }}</p>
                @endif
            </div>
        </div>
    @empty
        <div class="tracking-history__empty">
            No routing history has been recorded yet.
        </div>
    @endforelse

    @if (filled($nextStep))
        <div class="history-item is-upcoming">
            <div class="history-marker" aria-hidden="true">&#9675;</div>
            <div class="history-content">
                <h4>Next Step</h4>
                <p>{{ $nextStep }}</p>
            </div>
        </div>
    @endif
</div>
