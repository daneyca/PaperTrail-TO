@props([
    'label',
    'value' => '0',
    'description' => null,
    'tone' => 'blue',
    'icon' => 'document',
    'href' => null,
])

<x-dashboard.kpi-card
    :label="$label"
    :value="$value"
    :description="$description"
    :tone="$tone"
    :icon="$icon"
    :href="$href"
    {{ $attributes }}
/>
