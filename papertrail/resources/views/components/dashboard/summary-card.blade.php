@props([
    'label',
    'value' => '0',
    'description' => null,
    'tone' => 'blue',
    'icon' => 'document',
    'href' => null,
    'total' => null,
])

<x-dashboard.kpi-card
    :label="$label"
    :value="$value"
    :description="$description"
    :tone="$tone"
    :icon="$icon"
    :href="$href"
    :total="$total"
    {{ $attributes }}
/>
