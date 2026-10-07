@props([
    'label' => null,
    'title',
    'subtitle' => null,
    'actions' => null,
])

<section {{ $attributes->class(['pt-ui-page-header']) }}>
    <div>
        @if ($label)
            <p class="pt-ui-eyebrow">{{ $label }}</p>
        @endif

        <h1>{{ $title }}</h1>

        @if ($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>

    @if ($actions)
        <div class="pt-ui-page-actions">
            {{ $actions }}
        </div>
    @endif
</section>
