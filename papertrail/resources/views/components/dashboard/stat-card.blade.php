@props([
    'href' => null,
    'value' => '0',
    'label' => '',
    'accent' => 'navy',
    'icon' => null,
    'iconColor' => null,
    'sublabel' => null,
    'ariaLabel' => null,
])

@php
    $accentClass = match ($accent) {
        'gold', 'yellow' => 'summary-card--yellow',
        'green' => 'summary-card--green',
        'blue' => 'summary-card--blue',
        'red' => 'summary-card--red',
        'gray', 'slate' => 'summary-card--gray',
        default => 'summary-card--navy',
    };

    $cardClasses = trim('summary-card stat-card stat-card-modern ' . $accentClass . ' tone-' . ($accent === 'gold' ? 'gold' : $accent));
    $labelText = trim((string) $label);
    $accessibleLabel = $ariaLabel ?: ($labelText !== '' ? $labelText . ': ' . $value : null);
    $iconStyle = $iconColor ? 'color: ' . $iconColor . ';' : null;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cardClasses, 'aria-label' => $accessibleLabel]) }}>
        <span class="summary-card__accent" aria-hidden="true"></span>
        <span class="summary-card__content">
            <span class="summary-card__value">{{ $value }}</span>
            <span class="summary-card__label">{{ $label }}</span>
            @if ($sublabel)
                <span class="summary-card__sublabel">{{ $sublabel }}</span>
            @endif
        </span>
        @if ($icon)
            <span class="summary-card__icon" style="{{ $iconStyle }}" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    @switch($icon)
                        @case('check')
                            <path d="M20 6 9 17l-5-5" />
                            @break
                        @case('clock')
                            <circle cx="12" cy="12" r="8" />
                            <path d="M12 8v5l3 2" />
                            @break
                        @case('return')
                            <path d="M9 14 4 9l5-5" />
                            <path d="M4 9h10a6 6 0 0 1 0 12h-2" />
                            @break
                        @case('signature')
                            <path d="M4 18c3-5 4.5-5 5-3 .8 3.2 2.8 3.2 4.5.2 1.2-2.2 2.9-2 3.5.3.4 1.5 1.3 2.5 3 2.5" />
                            <path d="M5 21h14" />
                            @break
                        @default
                            <path d="M5 5h14v14H5z" />
                            <path d="M8 9h8M8 13h8" />
                    @endswitch
                </svg>
            </span>
        @endif
    </a>
@else
    <div {{ $attributes->merge(['class' => $cardClasses, 'aria-label' => $accessibleLabel]) }}>
        <span class="summary-card__accent" aria-hidden="true"></span>
        <span class="summary-card__content">
            <span class="summary-card__value">{{ $value }}</span>
            <span class="summary-card__label">{{ $label }}</span>
            @if ($sublabel)
                <span class="summary-card__sublabel">{{ $sublabel }}</span>
            @endif
        </span>
        @if ($icon)
            <span class="summary-card__icon" style="{{ $iconStyle }}" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    @switch($icon)
                        @case('check')
                            <path d="M20 6 9 17l-5-5" />
                            @break
                        @case('clock')
                            <circle cx="12" cy="12" r="8" />
                            <path d="M12 8v5l3 2" />
                            @break
                        @case('return')
                            <path d="M9 14 4 9l5-5" />
                            <path d="M4 9h10a6 6 0 0 1 0 12h-2" />
                            @break
                        @case('signature')
                            <path d="M4 18c3-5 4.5-5 5-3 .8 3.2 2.8 3.2 4.5.2 1.2-2.2 2.9-2 3.5.3.4 1.5 1.3 2.5 3 2.5" />
                            <path d="M5 21h14" />
                            @break
                        @default
                            <path d="M5 5h14v14H5z" />
                            <path d="M8 9h8M8 13h8" />
                    @endswitch
                </svg>
            </span>
        @endif
    </div>
@endif
