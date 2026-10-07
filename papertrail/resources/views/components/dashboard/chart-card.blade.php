@props([
    'title',
    'subtitle' => null,
    'chartId',
])

<article {{ $attributes->class(['procurement-chart-card']) }}>
    <div class="procurement-card-heading">
        <div>
            <p class="eyebrow">Analytics</p>
            <h2>{{ $title }}</h2>
            @if ($subtitle)
                <span>{{ $subtitle }}</span>
            @endif
        </div>
    </div>
    <div id="{{ $chartId }}" class="procurement-chart" aria-label="{{ $title }}"></div>
    <div class="procurement-chart-fallback" data-chart-fallback="{{ $chartId }}" hidden>
        Chart preview is unavailable while the chart library is loading.
    </div>
</article>
