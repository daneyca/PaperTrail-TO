@props([
    'steps' => [],
    'activeStep' => 1,
])

@php
    $workflowSteps = collect($steps)
        ->map(function ($step, $index) {
            if (is_array($step)) {
                return [
                    'number' => (int) ($step['number'] ?? $index + 1),
                    'title' => (string) ($step['title'] ?? $step['label'] ?? 'Step ' . ($index + 1)),
                ];
            }

            return [
                'number' => $index + 1,
                'title' => (string) $step,
            ];
        })
        ->filter(fn ($step) => filled($step['title']))
        ->values();

    $stepCount = $workflowSteps->count();
    $activeStep = max(1, min(max(1, $stepCount), (int) $activeStep));
@endphp

@if ($workflowSteps->isNotEmpty())
    <section {{ $attributes->class('document-workflow-steps no-print') }} aria-label="Procurement workflow steps">
        @foreach ($workflowSteps as $step)
            @php
                $stepNumber = (int) $step['number'];
                $stepState = $stepNumber < $activeStep ? 'is-complete' : ($stepNumber === $activeStep ? 'is-active' : 'is-upcoming');
            @endphp
            <article class="document-workflow-step {{ $stepState }}">
                <span>Step {{ $stepNumber }}</span>
                <strong>{{ $step['title'] }}</strong>
            </article>
        @endforeach
    </section>
@endif
