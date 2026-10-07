@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
    'backRoute' => null,
])

<section class="document-submenu-hero flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
    <div>
        @if ($eyebrow)
            <p class="text-xs font-black uppercase tracking-wide text-blue-700">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-950">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 max-w-2xl text-sm font-semibold leading-6 text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>

    <div class="flex shrink-0 items-center gap-2">
        @if ($backRoute && \Illuminate\Support\Facades\Route::has($backRoute))
            <a href="{{ route($backRoute) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-black text-slate-700 shadow-sm transition hover:border-blue-200 hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100">Back to Dashboard</a>
        @endif
    </div>
</section>
