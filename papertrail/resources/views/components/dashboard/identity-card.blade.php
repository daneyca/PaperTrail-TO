@props([
    'items' => [],
])

@php
    $items = collect($items)
        ->filter(fn ($item) => filled($item['value'] ?? null))
        ->values();
@endphp

@if ($items->isNotEmpty())
    <section {{ $attributes->class(['dashboard-identity-card']) }} aria-label="{{ $attributes->get('aria-label', 'Account identity') }}">
        @foreach ($items as $item)
            <div class="dashboard-identity-card__item">
                <span>{{ $item['label'] ?? 'Detail' }}</span>
                <strong>{{ $item['value'] }}</strong>
            </div>
        @endforeach
    </section>
@endif
