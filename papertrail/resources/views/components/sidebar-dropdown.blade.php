@props([
    'label',
    'active' => false,
    'open' => false,
    'icon' => null,
    'count' => 0,
])

@php
    $countValue = (int) $count;
@endphp

<div class="sidebar-dropdown {{ $active ? 'is-active' : '' }} {{ $open ? 'is-open' : '' }}" data-sidebar-dropdown>
    <button
        class="sidebar-dropdown-toggle"
        type="button"
        title="{{ $label }}"
        aria-expanded="{{ $open ? 'true' : 'false' }}"
        data-sidebar-dropdown-toggle
    >
        @if ($icon)
            <x-sidebar-icon :name="$icon" />
        @endif
        <span class="sidebar-link-label">{{ $label }}</span>
        @if ($countValue > 0)
            <span class="sidebar-count-badge {{ $label === 'Signatures' ? 'sidebar-count-badge--signature' : '' }}">
                {{ $countValue > 99 ? '99+' : number_format($countValue) }}
            </span>
        @endif
        <span class="sidebar-dropdown-arrow" aria-hidden="true">
            <svg viewBox="0 0 20 20" focusable="false">
                <path d="m6 8 4 4 4-4" />
            </svg>
        </span>
    </button>

    <div class="sidebar-submenu sidebar-dropdown-menu">
        {{ $slot }}
    </div>
</div>
