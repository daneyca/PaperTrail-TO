@props([
    'as' => 'article',
    'title' => null,
    'eyebrow' => null,
    'subtitle' => null,
])

<{{ $as }} {{ $attributes->class(['pt-ui-card']) }}>
    @if ($title || $eyebrow || $subtitle)
        <div class="pt-ui-card-header">
            <div>
                @if ($eyebrow)
                    <p class="pt-ui-eyebrow">{{ $eyebrow }}</p>
                @endif

                @if ($title)
                    <h2>{{ $title }}</h2>
                @endif

                @if ($subtitle)
                    <p>{{ $subtitle }}</p>
                @endif
            </div>
        </div>
    @endif

    <div class="pt-ui-card-body">
        {{ $slot }}
    </div>
</{{ $as }}>
