@props([
    'attachment',
    'label' => 'Extract Metadata',
])

<button
    type="button"
    class="ai-metadata-button no-print"
    data-ai-metadata-trigger
    data-extract-url="{{ route('ai.metadata.extract', $attachment) }}"
    data-attachment-name="{{ $attachment->displayName() }}"
    aria-label="{{ $label }} from {{ $attachment->displayName() }}"
    title="{{ $label }}"
>
    <span class="ai-metadata-button__icon" aria-hidden="true">AI</span>
    <span>{{ $label }}</span>
</button>

@once
    @push('modals')
        <x-ai.metadata-result-modal />
    @endpush
@endonce
