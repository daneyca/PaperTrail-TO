@php
    $maxValue = max(1, (int) collect($items)->max('value'));
    $showAmount = $showAmount ?? false;
@endphp

<div class="budget-bars">
    @forelse ($items as $item)
        @php
            $percentage = ((int) $item['value'] / $maxValue) * 100;
        @endphp
        <div class="budget-bar-row">
            <div class="budget-bar-copy">
                <strong>{{ $item['label'] }}</strong>
                <span>
                    {{ $item['value'] }} {{ Str::plural('record', (int) $item['value']) }}
                    @if ($showAmount && isset($item['amount']))
                        · PHP {{ number_format((float) $item['amount'], 2) }}
                    @endif
                </span>
            </div>
            <div class="budget-bar-track" aria-hidden="true">
                <span style="width: {{ $percentage }}%"></span>
            </div>
        </div>
    @empty
        <div class="empty-state"><strong>{{ $empty }}</strong><p>Reports use actual database records only.</p></div>
    @endforelse
</div>
