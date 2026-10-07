@props([
    'href' => null,
    'icon' => null,
    'label' => null,
    'tooltip' => null,
    'variant' => 'view',
    'type' => 'button',
    'iconOnly' => true,
    'disabled' => false,
])

@php
    $labelText = trim((string) ($label ?? $slot));
    $tooltipText = trim((string) ($tooltip ?: $labelText));
    $tag = $href && ! $disabled ? 'a' : ($disabled ? 'span' : 'button');
    $classes = trim('pt-action-button pt-action-button--'.$variant.' '.($iconOnly ? 'pt-action-button--icon-only' : 'pt-action-button--labeled'));
@endphp

@if ($tag === 'a')
    <a
        href="{{ $href }}"
        {{ $attributes->merge(['class' => $classes]) }}
        @if ($tooltipText !== '') aria-label="{{ $tooltipText }}" title="{{ $tooltipText }}" data-tooltip="{{ $tooltipText }}" @endif
    >
        @if ($icon)
            <x-papertrail.icon :name="$icon" class="pt-action-button__icon" />
        @endif
        <span class="{{ $iconOnly ? 'sr-only' : 'pt-action-button__label' }}">{{ $labelText }}</span>
    </a>
@elseif ($tag === 'span')
    <span
        {{ $attributes->merge(['class' => $classes]) }}
        aria-disabled="true"
        @if ($tooltipText !== '') aria-label="{{ $tooltipText }}" title="{{ $tooltipText }}" data-tooltip="{{ $tooltipText }}" @endif
    >
        @if ($icon)
            <x-papertrail.icon :name="$icon" class="pt-action-button__icon" />
        @endif
        <span class="{{ $iconOnly ? 'sr-only' : 'pt-action-button__label' }}">{{ $labelText }}</span>
    </span>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->merge(['class' => $classes]) }}
        @disabled($disabled)
        @if ($tooltipText !== '') aria-label="{{ $tooltipText }}" title="{{ $tooltipText }}" data-tooltip="{{ $tooltipText }}" @endif
    >
        @if ($icon)
            <x-papertrail.icon :name="$icon" class="pt-action-button__icon" />
        @endif
        <span class="{{ $iconOnly ? 'sr-only' : 'pt-action-button__label' }}">{{ $labelText }}</span>
    </button>
@endif
