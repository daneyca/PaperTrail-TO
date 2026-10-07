@props([
    'title' => 'Workflow Status Chart',
    'subtitle' => 'Created, submitted, approved, and returned records',
    'chartId' => 'workflowOverviewChart',
])

<x-dashboard.chart-card
    :title="$title"
    :subtitle="$subtitle"
    :chart-id="$chartId"
    {{ $attributes->class(['dashboard-workflow-chart']) }}
/>
