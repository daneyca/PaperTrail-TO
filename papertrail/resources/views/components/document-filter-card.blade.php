@props([
    'action',
    'method' => 'GET',
])

<form method="{{ $method }}" action="{{ $action }}" {{ $attributes->class(['document-filter-card']) }}>
    {{ $slot }}
</form>
