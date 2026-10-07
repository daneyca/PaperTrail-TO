@php
    $activeStep = max(1, min(3, (int) ($activeStep ?? 1)));
    $flowAware = (bool) ($flowAware ?? false);
    $steps = [
        ['number' => 1, 'title' => 'Select APP Project'],
        ['number' => 2, 'title' => 'Select APP Items'],
        ['number' => 3, 'title' => 'Create PR Form'],
    ];
@endphp

<section class="pr-workflow-steps no-print" aria-label="Purchase Request creation steps" @if ($flowAware) data-pr-flow-steps @endif>
    @foreach ($steps as $step)
        @php
            $stepNumber = $step['number'];
            $stepState = $stepNumber < $activeStep ? 'is-complete' : ($stepNumber === $activeStep ? 'is-active' : 'is-upcoming');
        @endphp
        <article
            class="pr-workflow-step {{ $stepState }}"
            @if ($flowAware) data-pr-flow-step="{{ $stepNumber }}" @endif
        >
            <span>Step {{ $stepNumber }}</span>
            <strong>{{ $step['title'] }}</strong>
        </article>
    @endforeach
</section>
