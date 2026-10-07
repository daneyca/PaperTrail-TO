@props([
    'eyebrow' => 'Source Selection',
    'title' => 'Select source',
    'description' => 'Choose the source record before opening the official document form.',
    'proceedLabel' => 'Proceed',
    'backUrl' => null,
    'backLabel' => 'Back',
    'autoshow' => false,
    'errorMessage' => 'Please choose a valid source before proceeding.',
    'workflowSteps' => [],
    'formStep' => null,
])

@php
    $workflowSteps = collect($workflowSteps)->values();
    $workflowStepCount = $workflowSteps->count();
    $formActiveStep = $workflowStepCount > 0
        ? max(1, min($workflowStepCount, (int) ($formStep ?? max(2, $workflowStepCount - 1))))
        : 1;
@endphp

<section
    {{ $attributes->merge(['class' => 'document-create-flow']) }}
    data-document-create-flow
    data-document-create-autoshow="{{ $autoshow ? 'true' : 'false' }}"
>
    <section class="document-create-source-card no-print" data-document-create-source aria-label="{{ $title }}">
        <div class="document-create-source-card__header">
            <div class="document-create-source-card__step" aria-hidden="true">1</div>
            <div>
                <p class="eyebrow">{{ $eyebrow }}</p>
                <h2>{{ $title }}</h2>
                @if (trim((string) $description) !== '')
                    <p>{{ $description }}</p>
                @endif
            </div>
        </div>

        <x-documents.workflow-steps :steps="$workflowSteps" :active-step="1" />

        <div class="document-create-source-card__body">
            {{ $source ?? $slot }}
        </div>

        <div class="document-create-source-card__actions">
            @if ($backUrl)
                <a href="{{ $backUrl }}" class="document-create-secondary-action">{{ $backLabel }}</a>
            @endif

            <button type="button" class="document-create-proceed-button" data-document-create-proceed>
                {{ $proceedLabel }}
            </button>
        </div>

        <p class="document-create-source-error" data-document-create-error hidden>{{ $errorMessage }}</p>
    </section>

    <div class="document-create-form" data-document-create-form>
        <x-documents.workflow-steps :steps="$workflowSteps" :active-step="$formActiveStep" />
        {{ $form ?? '' }}
    </div>
</section>
