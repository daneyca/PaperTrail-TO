@props(['href', 'count' => 0, 'total' => 0, 'label' => '', 'accent' => 'blue', 'icon' => 'document', 'active' => false])
@php
    $count = max(0, (int) $count);
    $total = max(0, (int) $total);
    $percentage = $total > 0 ? round(min(100, $count / $total * 100), 1) : 0;
    $accent = in_array($accent, ['navy', 'gold', 'blue', 'green', 'red'], true) ? $accent : 'blue';
@endphp
<a href="{{ $href }}" {{ $attributes->class(['workflow-stat-card', 'workflow-stat-card--'.$accent, 'is-active' => $active]) }}
   aria-label="{{ $label }}: {{ number_format($count) }} of {{ number_format($total) }} PPMPs"
   @if ($active) aria-current="true" @endif>
    <span class="workflow-stat-card__main">
        <span class="workflow-stat-card__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" focusable="false">
                @switch($icon)
                    @case('layers')
                        <path d="m12 2.5-9 5 9 5 9-5-9-5Z" fill="currentColor" stroke="none" />
                        <path d="m3 12 9 5 9-5M3 17l9 5 9-5" />
                        @break
                    @case('send')
                        <path d="m21 3-7 18-4-7-7-4 18-7ZM10 14 21 3" />
                        @break
                    @case('consolidate')
                        <rect x="3" y="3" width="7" height="7" rx="1.5" />
                        <rect x="14" y="3" width="7" height="7" rx="1.5" />
                        <path d="M6.5 13v2h11v-2M12 15v6m-3-3 3 3 3-3" />
                        @break
                    @case('return')
                        <path d="m8 4-5 5 5 5M3 9h10a6 6 0 0 1 0 12h-2" />
                        @break
                    @case('check')
                        <circle cx="12" cy="12" r="9" />
                        <path d="m7.5 12 3 3 6-6" />
                        @break
                    @default
                        <path d="M6 3h8l5 5v13H6V3Zm8 0v5h5" />
                @endswitch
            </svg>
        </span>
        <span class="workflow-stat-card__copy">
            <span class="workflow-stat-card__value">{{ number_format($count) }}</span>
            <span class="workflow-stat-card__label">{{ $label }}</span>
        </span>
    </span>
    <span class="workflow-stat-card__meter" aria-hidden="true">
        <span style="width: {{ $percentage }}%"></span>
    </span>
</a>

@once
    @push('page-styles')
        <link rel="stylesheet" href="{{ asset('css/workflow-summary.css') }}?v={{ filemtime(public_path('css/workflow-summary.css')) }}">
    @endpush
@endonce
