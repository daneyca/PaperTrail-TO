@props([
    'card',
])

@php
    $accent = $card['accent'] ?? 'blue';
    $disabled = (bool) ($card['disabled'] ?? false);
    $disabledBadge = $card['badge'] ?? 'Unavailable';
    $tone = match ($accent) {
        'violet' => [
            'tile' => 'hover:border-violet-300 hover:bg-violet-50/40 focus:ring-violet-100',
            'icon' => 'bg-violet-50 text-violet-700 ring-violet-100',
            'badge' => 'bg-violet-600 text-white',
        ],
        'emerald', 'green' => [
            'tile' => 'hover:border-emerald-300 hover:bg-emerald-50/40 focus:ring-emerald-100',
            'icon' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            'badge' => 'bg-emerald-600 text-white',
        ],
        'teal' => [
            'tile' => 'hover:border-cyan-300 hover:bg-cyan-50/40 focus:ring-cyan-100',
            'icon' => 'bg-cyan-50 text-cyan-700 ring-cyan-100',
            'badge' => 'bg-cyan-600 text-white',
        ],
        'indigo' => [
            'tile' => 'hover:border-indigo-300 hover:bg-indigo-50/40 focus:ring-indigo-100',
            'icon' => 'bg-indigo-50 text-indigo-700 ring-indigo-100',
            'badge' => 'bg-indigo-600 text-white',
        ],
        'orange' => [
            'tile' => 'hover:border-orange-300 hover:bg-orange-50/40 focus:ring-orange-100',
            'icon' => 'bg-orange-50 text-orange-700 ring-orange-100',
            'badge' => 'bg-orange-600 text-white',
        ],
        'amber' => [
            'tile' => 'hover:border-amber-300 hover:bg-amber-50/40 focus:ring-amber-100',
            'icon' => 'bg-amber-50 text-amber-700 ring-amber-100',
            'badge' => 'bg-amber-500 text-slate-950',
        ],
        'rose' => [
            'tile' => 'hover:border-rose-300 hover:bg-rose-50/40 focus:ring-rose-100',
            'icon' => 'bg-rose-50 text-rose-700 ring-rose-100',
            'badge' => 'bg-rose-600 text-white',
        ],
        'slate' => [
            'tile' => 'hover:border-slate-300 hover:bg-slate-50 focus:ring-slate-100',
            'icon' => 'bg-slate-100 text-slate-600 ring-slate-200',
            'badge' => 'bg-slate-500 text-white',
        ],
        default => [
            'tile' => 'hover:border-blue-300 hover:bg-blue-50/40 focus:ring-blue-100',
            'icon' => 'bg-blue-50 text-blue-700 ring-blue-100',
            'badge' => 'bg-blue-600 text-white',
        ],
    };

    $baseClasses = 'document-submenu-tile document-submenu-tile--' . $accent . ' relative flex h-20 flex-col items-center justify-center rounded-xl border border-slate-200 bg-white px-2 py-2 text-center no-underline shadow-sm transition focus:outline-none focus:ring-4';
@endphp

@if (! $disabled && ! empty($card['url']))
    <a
        href="{{ $card['url'] }}"
        class="{{ $baseClasses }} {{ $tone['tile'] }} hover:-translate-y-0.5 hover:shadow-md"
        title="{{ $card['description'] ?? $card['title'] }}"
    >
        @if (array_key_exists('count', $card) && $card['count'] !== null)
            <span class="document-submenu-tile__badge absolute right-1.5 top-1.5 min-w-5 rounded-full px-1.5 py-0.5 text-[10px] font-black leading-4 {{ $tone['badge'] }}">
                {{ number_format((int) $card['count']) }}
            </span>
        @endif

        <span class="document-submenu-tile__icon mb-1.5 grid h-8 w-8 place-items-center rounded-full ring-1 {{ $tone['icon'] }}">
            <x-papertrail.icon :name="$card['icon'] ?? 'document'" class="h-4 w-4" />
        </span>

        <span class="document-submenu-tile__title line-clamp-2 text-[11px] font-black leading-tight text-slate-800">{{ $card['title'] }}</span>
    </a>
@else
    <div
        class="{{ $baseClasses }} document-submenu-tile--disabled border-dashed bg-slate-50 text-slate-500"
        title="{{ $card['description'] ?? 'This action is unavailable.' }}"
        aria-disabled="true"
    >
        <span class="document-submenu-tile__badge absolute right-1.5 top-1.5 rounded-full border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-black leading-4 text-slate-500">
            {{ $disabledBadge }}
        </span>

        <span class="document-submenu-tile__icon mb-1.5 grid h-8 w-8 place-items-center rounded-full ring-1 {{ $tone['icon'] }}">
            <x-papertrail.icon :name="$card['icon'] ?? 'document'" class="h-4 w-4" />
        </span>

        <span class="document-submenu-tile__title line-clamp-2 text-[11px] font-black leading-tight text-slate-600">{{ $card['title'] }}</span>
    </div>
@endif
