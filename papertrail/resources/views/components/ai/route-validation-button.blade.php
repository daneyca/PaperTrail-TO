@props([
    'documentType',
    'documentId',
    'trackingNumber' => null,
    'label' => 'AI Check Routing',
    'variant' => 'floating',
])

@php
    $buttonLabel = 'Routing';
    $tooltipLabel = 'AI Routing Assistance';
    $buttonVariant = in_array($variant, ['inline-icon', 'inline'], true) ? $variant : 'inline-icon';
@endphp

<button
    type="button"
    class="ai-route-validation-button ai-route-validation-button--{{ $buttonVariant }} ai-feature-button ai-feature-button--routing no-print"
    data-ai-route-validation-trigger
    data-ai-feature-button
    data-document-type="{{ $documentType }}"
    data-document-id="{{ $documentId }}"
    data-tracking-number="{{ $trackingNumber }}"
    data-tooltip="{{ $tooltipLabel }}"
    aria-label="{{ $tooltipLabel }}"
    title="{{ $tooltipLabel }}"
>
    <span class="ai-route-validation-button__orb ai-feature-button__icon" aria-hidden="true">
        <x-ai.feature-icon type="routing" />
    </span>
    <span class="ai-route-validation-button__label ai-feature-button__label">{{ $buttonLabel }}</span>
</button>

@once
    @push('modals')
        <x-ai.route-validation-result-modal />
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.ai-route-validation-button--floating').forEach((button) => {
                    if (button.parentElement !== document.body) {
                        document.body.appendChild(button);
                    }
                });
            });
        </script>
    @endpush
@endonce
