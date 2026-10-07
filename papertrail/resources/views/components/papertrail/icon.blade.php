@props([
    'name' => 'document',
])

<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
    @switch($name)
        @case('ppmp')
            <path d="M7 3.75h7.5L18 7.25v13H7a2 2 0 0 1-2-2V5.75a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            <path d="M14.5 3.75v3.5H18M8.5 11h6.5M8.5 14h6.5M8.5 17h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            @break

        @case('app')
            <path d="M6.5 4.25h11a1.75 1.75 0 0 1 1.75 1.75v12A1.75 1.75 0 0 1 17.5 19.75h-11A1.75 1.75 0 0 1 4.75 18V6A1.75 1.75 0 0 1 6.5 4.25Z" stroke="currentColor" stroke-width="1.8" />
            <path d="M8 8h8M8 11.5h8M8 15h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            @break

        @case('pr')
            <path d="M5 5.25h2.1l1.1 8.1a2 2 0 0 0 2 1.75h5.7a2 2 0 0 0 1.93-1.48l1.05-4.12H8.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M10 19.25h.01M16.5 19.25h.01" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" />
            @break

        @case('po')
            <path d="M6 4.5h12v15H6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            <path d="M9 8h6M9 11h6M9 14h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            @break

        @case('signature')
            <path d="M4.5 17.5c2.7-5.2 4.7-8 6-8.4.9-.3 1.45.25 1.45 1.15 0 1.65-2.3 3.8-1.25 4.55 1.3.9 3.2-1.35 4.15-.35.55.58.1 1.7-.55 2.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M4 20h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            @break

        @case('check')
            <path d="M20 6.75 9.5 17.25 4 11.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            @break

        @case('return')
            <path d="M8.25 7.25 4.5 11l3.75 3.75" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M5 11h9.5a4.5 4.5 0 0 1 0 9H13" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
            @break

        @case('clock')
            <path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" stroke="currentColor" stroke-width="1.8" />
            <path d="M12 7.5V12l3 1.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            @break

        @case('search')
            <path d="m20 20-4.2-4.2M10.8 17.1a6.3 6.3 0 1 1 0-12.6 6.3 6.3 0 0 1 0 12.6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            @break

        @case('filter')
            <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            @break

        @case('upload')
            <path d="M12 15.5V4.75M8.25 8.5 12 4.75l3.75 3.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M5 14.5v3.75A1.75 1.75 0 0 0 6.75 20h10.5A1.75 1.75 0 0 0 19 18.25V14.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            @break

        @case('download')
            <path d="M12 4.75v9.5M8.25 10.5 12 14.25l3.75-3.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M5 15.75v2.5A1.75 1.75 0 0 0 6.75 20h10.5A1.75 1.75 0 0 0 19 18.25v-2.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            @break

        @case('open')
            <path d="M14 4.75h5.25V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M19.25 4.75 11.5 12.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            <path d="M10.5 6.25H6.75A1.75 1.75 0 0 0 5 8v9.25A1.75 1.75 0 0 0 6.75 19h9.25a1.75 1.75 0 0 0 1.75-1.75V13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            @break

        @case('view')
            <path d="M3.75 12s2.9-5.25 8.25-5.25S20.25 12 20.25 12 17.35 17.25 12 17.25 3.75 12 3.75 12Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            <path d="M12 14.25a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z" stroke="currentColor" stroke-width="1.8" />
            @break

        @case('edit')
            <path d="M4.75 19.25h4.1l10.2-10.2a2.4 2.4 0 0 0-3.4-3.4L5.45 15.85l-.7 3.4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            <path d="m14.25 7.05 2.7 2.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            @break

        @case('delete')
            <path d="M5.25 7.25h13.5M9.25 7.25V5.5a1.25 1.25 0 0 1 1.25-1.25h3A1.25 1.25 0 0 1 14.75 5.5v1.75M7.25 7.25l.8 11a1.75 1.75 0 0 0 1.75 1.62h4.4a1.75 1.75 0 0 0 1.75-1.62l.8-11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M10.25 11v5M13.75 11v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            @break

        @case('key')
            <path d="M9.75 14.25a4.25 4.25 0 1 1 3-7.25 4.25 4.25 0 0 1-3 7.25Z" stroke="currentColor" stroke-width="1.8" />
            <path d="m13 11 6.25 6.25M16.25 14.25l-1.75 1.75M18.25 16.25l-1.75 1.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            @break

        @case('power')
            <path d="M12 3.75v8" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
            <path d="M7.05 6.95a7 7 0 1 0 9.9 0" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
            @break

        @case('users')
            <path d="M9.5 11.75a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM3.75 19.25a5.75 5.75 0 0 1 11.5 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            <path d="M16.25 10.75a2.75 2.75 0 1 0 0-5.5M17.25 19.25h3a4.75 4.75 0 0 0-4.75-4.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            @break

        @case('shield')
            <path d="M12 21s7-3.25 7-9.25v-5.5L12 3.75l-7 2.5v5.5C5 17.75 12 21 12 21Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            <path d="m8.75 12.25 2.1 2.1 4.4-4.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            @break

        @case('print')
            <path d="M7 8V4.75h10V8M7 16.25H5.75A1.75 1.75 0 0 1 4 14.5v-4.25A1.75 1.75 0 0 1 5.75 8.5h12.5A1.75 1.75 0 0 1 20 10.25v4.25a1.75 1.75 0 0 1-1.75 1.75H17" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            <path d="M7 13.5h10v5.75H7zM16.5 11.25h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            @break

        @case('truck')
            <path d="M3.75 6.5h10.5v9.75H3.75zM14.25 10h2.8l2.7 2.8v3.45h-5.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            <path d="M7.25 18.25a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM17.25 18.25a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" stroke="currentColor" stroke-width="1.8" />
            <path d="M5.25 9h5.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            @break

        @case('bell')
            <path d="M18 9.75a6 6 0 0 0-12 0c0 6-2.25 6.25-2.25 6.25h16.5S18 15.75 18 9.75Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            <path d="M9.75 19a2.25 2.25 0 0 0 4.5 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            @break

        @case('close')
            <path d="m6.5 6.5 11 11M17.5 6.5l-11 11" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            @break

        @case('mail')
            <path d="M5.75 6.25h12.5A1.75 1.75 0 0 1 20 8v8a1.75 1.75 0 0 1-1.75 1.75H5.75A1.75 1.75 0 0 1 4 16V8a1.75 1.75 0 0 1 1.75-1.75Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            <path d="m5 8 7 5 7-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            @break

        @case('spark')
            <path d="M12 3.75 13.55 9 19 10.5l-5.45 1.5L12 17.25 10.45 12 5 10.5 10.45 9 12 3.75Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            <path d="M18.25 15.75 19 18l2.25.75L19 19.5l-.75 2.25-.75-2.25-2.25-.75 2.25-.75.75-2.25Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
            @break

        @case('bot')
            <path d="M12 4v2.25M8 4.75h8a3 3 0 0 1 3 3v6.5a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3v-6.5a3 3 0 0 1 3-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            <path d="M9 11h.01M15 11h.01M9.5 14.25h5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            <path d="M7 19.5h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            @break

        @default
            <path d="M7 3.75h7.5L18 7.25v13H7a2 2 0 0 1-2-2V5.75a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            <path d="M14.5 3.75v3.5H18M8.5 12h6.5M8.5 15h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
    @endswitch
</svg>
