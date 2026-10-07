@props([
    'records' => [],
])

<x-documents.records-list
    title="Recent Document Records"
    subtitle="Documents created, submitted, or routed through this module will appear here."
    :records="$records"
/>
