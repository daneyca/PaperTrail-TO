@props([
    'steps' => [],
])

<aside class="pt-ui-card">
    <div class="pt-ui-card-header">
        <div>
            <p class="pt-ui-eyebrow">Guide</p>
            <h2>How this module works</h2>
        </div>
    </div>
    <div class="pt-ui-card-body">
        @if ($steps)
            <ol class="space-y-3">
                @foreach ($steps as $step)
                    <li class="flex gap-3 text-sm font-semibold leading-6 text-slate-600">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-blue-50 text-xs font-black text-blue-700">{{ $loop->iteration }}</span>
                        <span>{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
        @else
            <p class="text-sm font-semibold text-slate-500">No module guide is configured yet.</p>
        @endif
    </div>
</aside>
