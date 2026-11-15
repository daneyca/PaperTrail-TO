@props([
    'action',
    'method' => 'GET',
])

@if (strtoupper($method) === 'GET' && ! $attributes->has('data-app-year-form'))
    <x-navbar-filter-form :action="$action">{{ $slot }}</x-navbar-filter-form>
@else
    <form method="{{ $method }}" action="{{ $action }}" {{ $attributes->class(['document-filter-card']) }}>
        {{ $slot }}
    </form>
@endif
