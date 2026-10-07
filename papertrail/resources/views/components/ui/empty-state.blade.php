@props([
    'title' => 'No records found',
    'message' => 'Records will appear here once activity is available.',
    'icon' => 'document',
])

<div {{ $attributes->class(['pt-ui-empty-state']) }}>
    <span class="pt-ui-empty-icon" aria-hidden="true">
        <x-papertrail.icon :name="$icon" />
    </span>
    <strong>{{ $title }}</strong>
    <p>{{ $message }}</p>
    @if (trim((string) $slot) !== '')
        <div class="pt-ui-empty-actions">
            {{ $slot }}
        </div>
    @endif
</div>
