@props([
    'label',
    'value' => '0',
    'description' => null,
    'tone' => 'blue',
    'icon' => 'document',
    'href' => null,
    'total' => null,
])

<x-dashboard.workflow-stat-card
    :href="$href"
    :value="$value"
    :total="$total"
    :label="$label"
    :description="$description"
    :accent="$tone"
    :icon="$icon"
    {{ $attributes }}
/>
