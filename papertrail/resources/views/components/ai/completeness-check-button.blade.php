@props([
    'documentType',
    'documentId',
    'trackingNumber' => null,
    'label' => 'AI Check Completeness',
    'variant' => 'floating',
])

@php
    $buttonLabel = 'Completeness';
    $tooltipLabel = 'AI Completeness Checking';
    $requestedVariant = $variant;
    $buttonVariant = in_array($variant, ['inline-icon', 'inline'], true) ? $variant : 'inline-icon';
@endphp

@if ($requestedVariant === 'floating')
    <div class="ai-document-tools-group no-print" role="group" aria-label="AI Document Assistance Tools">
        <span class="ai-document-tools-group__label">AI Tools</span>
@endif

<button
    type="button"
    class="ai-completeness-button ai-completeness-button--{{ $buttonVariant }} ai-feature-button ai-feature-button--completeness no-print"
    data-ai-completeness-trigger
    data-ai-feature-button
    data-document-type="{{ $documentType }}"
    data-document-id="{{ $documentId }}"
    data-tracking-number="{{ $trackingNumber }}"
    data-tooltip="{{ $tooltipLabel }}"
    aria-label="{{ $tooltipLabel }}"
    title="{{ $tooltipLabel }}"
>
    <span class="ai-completeness-button__orb ai-feature-button__icon" aria-hidden="true">
        <x-ai.feature-icon type="completeness" />
    </span>
    <span class="ai-completeness-button__label ai-feature-button__label">{{ $buttonLabel }}</span>
</button>

@if ($requestedVariant === 'floating')
    <x-ai.route-validation-button
        :document-type="$documentType"
        :document-id="$documentId"
        :tracking-number="$trackingNumber"
        label="AI Check Routing"
        variant="inline-icon"
    />
    <x-ai.delay-risk-button
        :document-type="$documentType"
        :document-id="$documentId"
        :tracking-number="$trackingNumber"
        label="AI Delay Risk"
        variant="inline-icon"
    />
    </div>
@endif

@once
    @push('modals')
        <x-ai.completeness-result-modal />
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.ai-completeness-button--floating').forEach((button) => {
                    if (button.parentElement !== document.body) {
                        document.body.appendChild(button);
                    }
                });
            });
        </script>
    @endpush
@endonce
