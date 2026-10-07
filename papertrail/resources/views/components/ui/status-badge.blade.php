@props([
    'status' => 'Status',
    'tone' => null,
])

@php
    $label = trim((string) $status) ?: 'Status';
    $normalized = strtolower(str_replace([' ', '_'], '-', $tone ?: $label));
    $toneClass = match (true) {
        str_contains($normalized, 'draft') => 'pt-ui-status--draft',
        str_contains($normalized, 'return'), str_contains($normalized, 'reject'), str_contains($normalized, 'failed') => 'pt-ui-status--danger',
        str_contains($normalized, 'approved'), str_contains($normalized, 'accepted'), str_contains($normalized, 'complete'), str_contains($normalized, 'signed') => 'pt-ui-status--success',
        str_contains($normalized, 'pending'), str_contains($normalized, 'action') => 'pt-ui-status--warning',
        str_contains($normalized, 'review'), str_contains($normalized, 'process'), str_contains($normalized, 'submitted') => 'pt-ui-status--info',
        default => 'pt-ui-status--neutral',
    };
@endphp

<span {{ $attributes->class(['pt-ui-status', $toneClass]) }}>{{ $label }}</span>
