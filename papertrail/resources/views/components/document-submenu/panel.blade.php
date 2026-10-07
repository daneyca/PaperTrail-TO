@props([
    'title' => 'Document Submenu',
    'cards' => [],
])

<section class="document-submenu-panel overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="{{ $title }} actions">
    <x-document-submenu.header :title="$title" :count="count($cards)" />
    <x-document-submenu.grid :cards="$cards" />
</section>
