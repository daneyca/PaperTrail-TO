@props([
    'type' => 'Document',
])

@php
    $label = trim((string) $type) ?: 'Document';
    $typeClass = 'pt-ui-doc-type--' . strtolower(str_replace([' ', '_'], '-', $label));
@endphp

<span {{ $attributes->class(['pt-ui-doc-type', $typeClass]) }}>{{ $label }}</span>
