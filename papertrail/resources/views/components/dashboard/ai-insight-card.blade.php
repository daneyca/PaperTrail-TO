@props([
    'metrics' => [],
    'summaryUrl' => null,
])

@php
    $items = [
        ['label' => 'High Risk Documents', 'value' => $metrics['high_risk_documents'] ?? 0, 'tone' => 'danger'],
        ['label' => 'Most Missing Requirement', 'value' => $metrics['most_missing_requirement'] ?? 'None recorded', 'tone' => 'warning'],
        ['label' => 'Most Delayed Stage', 'value' => $metrics['most_delayed_stage'] ?? 'No delay data', 'tone' => 'blue'],
        ['label' => 'Average Completeness Score', 'value' => $metrics['average_completeness_score'] ?? 'Pending', 'tone' => 'success'],
    ];
@endphp

<article {{ $attributes->class(['procurement-ai-insights']) }}>
    <div class="procurement-card-heading procurement-card-heading--split">
        <div>
            <p class="eyebrow">AI Procurement Insights</p>
            <h2>Operational signals</h2>
            <span>Uses existing PaperTrail AI checks and workflow data.</span>
        </div>

        @if ($summaryUrl)
            <a class="procurement-secondary-action" href="{{ $summaryUrl }}">Generate AI Summary</a>
        @endif
    </div>

    <div class="procurement-insight-grid">
        @foreach ($items as $item)
            <div class="procurement-insight-item procurement-insight-item--{{ $item['tone'] }}">
                <span>{{ $item['label'] }}</span>
                <strong>{{ $item['value'] }}</strong>
            </div>
        @endforeach
    </div>
</article>
