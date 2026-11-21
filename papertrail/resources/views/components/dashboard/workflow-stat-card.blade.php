@props([
    'href' => null,
    'count' => 0,
    'value' => null,
    'total' => null,
    'label' => '',
    'description' => null,
    'accent' => 'blue',
    'icon' => 'document',
    'iconColor' => null,
    'active' => false,
    'ariaLabel' => null,
])
@php
    $count = max(0, (int) $count);
    $displayValue = $value === null ? number_format($count) : (string) $value;
    $plainNumber = str_replace(',', '', trim($displayValue));
    $numericValue = is_numeric($plainNumber) ? max(0, (float) $plainNumber) : null;
    $total = $total === null ? null : max(0, (float) $total);
    // A ratio is shown only when the page supplies a meaningful total.
    $percentage = $total !== null
        ? ($total > 0 ? round(min(100, ($numericValue ?? $count) / $total * 100), 1) : 0)
        : (($numericValue ?? 1) > 0 ? 100 : 0);
    $accent = match ($accent) { 'yellow' => 'gold', 'slate' => 'gray', default => $accent };
    $accent = in_array($accent, ['navy', 'gold', 'blue', 'green', 'red', 'purple', 'orange', 'gray'], true) ? $accent : 'blue';
    $tag = $href ? 'a' : 'article';
    $longValue = $numericValue === null || mb_strlen($displayValue) > 11;
    $accessibleLabel = $ariaLabel ?: $label.': '.$displayValue;
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif
   {{ $attributes->class(['workflow-stat-card', 'workflow-stat-card--'.$accent, 'workflow-stat-card--long-value' => $longValue, 'is-active' => $active]) }}
   aria-label="{{ $accessibleLabel }}"
   @if ($active) aria-current="true" @endif>
    <span class="workflow-stat-card__main">
        <span class="workflow-stat-card__icon" @if ($iconColor) style="color: {{ $iconColor }}" @endif aria-hidden="true">
            @if (in_array($icon, ['layers', 'send', 'consolidate', 'return', 'check'], true))
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
            @else
                <x-papertrail.icon :name="$icon" />
            @endif
        </span>
        <span class="workflow-stat-card__copy">
            <span class="workflow-stat-card__value">{{ $displayValue }}</span>
            <span class="workflow-stat-card__label">{{ $label }}</span>
            @if ($description)
                <small class="workflow-stat-card__description">{{ $description }}</small>
            @endif
        </span>
    </span>
    <span class="workflow-stat-card__meter" aria-hidden="true">
        <span style="width: {{ $percentage }}%"></span>
    </span>
</{{ $tag }}>
