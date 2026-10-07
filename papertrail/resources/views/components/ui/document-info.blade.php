@props([
    'eyebrow' => null,
    'title' => 'Document Details',
    'description' => null,
    'items' => [],
])

@php
    $documentInfoItems = collect($items)->filter(fn ($item) => filled($item['label'] ?? null));
@endphp

<section {{ $attributes->merge(['class' => 'pt-document-info']) }}>
    <header class="pt-document-info__header">
        <div>
            @if ($eyebrow)
                <p class="pt-document-info__eyebrow">{{ $eyebrow }}</p>
            @endif
            <h2 class="pt-document-info__title">{{ $title }}</h2>
            @if ($description)
                <p class="pt-document-info__description">{{ $description }}</p>
            @endif
        </div>

        @isset($status)
            <div class="pt-document-info__status">
                {{ $status }}
            </div>
        @endisset
    </header>

    <dl class="pt-document-info__grid">
        @foreach ($documentInfoItems as $item)
            @php
                $value = $item['value'] ?? null;
                $displayValue = ($value === 0 || $value === '0' || filled($value)) ? $value : ($item['empty'] ?? 'N/A');
            @endphp
            <div class="pt-document-info__item">
                <dt class="pt-document-info__label">{{ $item['label'] }}</dt>
                <dd class="pt-document-info__value">{{ $displayValue }}</dd>
            </div>
        @endforeach
    </dl>
</section>
