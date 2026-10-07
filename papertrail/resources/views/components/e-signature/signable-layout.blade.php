@props([
    'sidebarLabel' => 'Document actions',
])

<div {{ $attributes->class(['signable-page']) }}>
    <main class="signable-page__main">
        <section class="signable-document-shell" aria-label="Signable document preview">
            {{ $document ?? $slot }}
        </section>
    </main>

    @isset($sidebar)
        <aside class="signable-page__sidebar screen-only" aria-label="{{ $sidebarLabel }}">
            {{ $sidebar }}
        </aside>
    @endisset
</div>
