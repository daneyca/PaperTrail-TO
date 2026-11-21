@props([
    'href' => null,
    'value' => '0',
    'label' => '',
    'accent' => 'navy',
    'icon' => null,
    'iconColor' => null,
    'sublabel' => null,
    'ariaLabel' => null,
    'total' => null,
])

@php
    $labelText = \Illuminate\Support\Str::lower((string) $label);
    $contains = fn (array $words) => \Illuminate\Support\Str::contains($labelText, $words);
    $cardIcon = $icon ?: match (true) {
        $contains(['budget', 'amount', 'fund', 'cost']) => 'po',
        $contains(['signature', 'signed']) => 'signature',
        $contains(['return', 'denied', 'reject', 'failed', 'inactive', 'cancel']) => 'return',
        $contains(['user', 'account', 'staff', 'member', 'office']) => 'users',
        $contains(['role', 'permission']) => 'shield',
        $contains(['draft']) => 'document',
        $contains(['pending', 'awaiting', 'review', 'waiting', 'overdue']) => 'clock',
        $contains(['submit', 'forward', 'routed', 'sent']) => 'send',
        $contains(['accept', 'approv', 'complet', 'verified', 'active', 'ready', 'received']) => 'check',
        default => 'layers',
    };
    $active = \Illuminate\Support\Str::contains((string) $attributes->get('class'), 'is-filter-active');
@endphp

<x-dashboard.workflow-stat-card
    :href="$href"
    :value="$value"
    :total="$total"
    :label="$label"
    :description="$sublabel"
    :accent="$accent"
    :icon="$cardIcon"
    :icon-color="$iconColor"
    :active="$active"
    :aria-label="$ariaLabel"
    {{ $attributes }}
/>
