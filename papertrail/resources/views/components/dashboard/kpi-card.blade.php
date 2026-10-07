@props([
    'label',
    'value' => '0',
    'description' => null,
    'tone' => 'blue',
    'icon' => 'document',
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'article';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['procurement-kpi-card', 'procurement-kpi-card--' . $tone]) }}
>
    <span class="procurement-kpi-card__icon" aria-hidden="true">
        <x-papertrail.icon :name="$icon" />
    </span>
    <span class="procurement-kpi-card__body">
        <strong>{{ $value }}</strong>
        <span>{{ $label }}</span>
        @if ($description)
            <small>{{ $description }}</small>
        @endif
    </span>
    <span class="procurement-kpi-card__bar" aria-hidden="true"></span>
</{{ $tag }}>
