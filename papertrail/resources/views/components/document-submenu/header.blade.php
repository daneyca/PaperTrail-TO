@props([
    'title' => 'Document Submenu',
    'count' => 0,
])

<div class="document-submenu-panel-header flex items-center justify-between gap-3 bg-blue-900 px-4 py-3 text-white">
    <h2 class="text-sm font-black uppercase tracking-wide">{{ $title }}</h2>
    <span class="rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-bold text-blue-50">
        {{ $count }} actions
    </span>
</div>
