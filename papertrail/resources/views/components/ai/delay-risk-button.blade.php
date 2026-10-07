@props([
    'documentType',
    'documentId',
    'trackingNumber' => null,
    'label' => 'AI Delay Risk',
    'variant' => 'floating',
])

@php
    $buttonLabel = 'Delay Risk';
    $tooltipLabel = 'AI Delay Risk Analysis';
    $buttonVariant = in_array($variant, ['inline-icon', 'inline'], true) ? $variant : 'inline-icon';
@endphp

<button
    type="button"
    class="ai-delay-risk-button ai-delay-risk-button--{{ $buttonVariant }} ai-feature-button ai-feature-button--risk no-print"
    data-ai-delay-risk-trigger
    data-ai-feature-button
    data-endpoint="{{ route('ai.delay-risk.analyze', [$documentType, $documentId]) }}"
    data-document-type="{{ $documentType }}"
    data-document-id="{{ $documentId }}"
    data-tracking-number="{{ $trackingNumber }}"
    data-tooltip="{{ $tooltipLabel }}"
    aria-label="{{ $tooltipLabel }}"
    title="{{ $tooltipLabel }}"
>
    <span class="ai-delay-risk-button__orb ai-feature-button__icon" aria-hidden="true">
        <x-ai.feature-icon type="risk" />
    </span>
    <span class="ai-delay-risk-button__label ai-feature-button__label">{{ $buttonLabel }}</span>
</button>

@once
    @push('modals')
        <x-ai.delay-risk-modal />
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.ai-delay-risk-button--floating').forEach((button) => {
                    if (button.parentElement !== document.body) {
                        document.body.appendChild(button);
                    }
                });
            });
        </script>
    @endpush
@endonce
