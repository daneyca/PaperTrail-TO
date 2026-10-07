@props([
    'cards' => [],
])

<div class="document-submenu-action-grid grid grid-cols-2 gap-2 p-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-7 2xl:grid-cols-9" aria-label="Document submenu actions">
    @foreach ($cards as $card)
        <x-document-submenu.tile :card="$card" />
    @endforeach
</div>
