@props(['action', 'filters' => [], 'fields' => [], 'label' => 'PPMPs', 'total' => 0,
    'context' => [], 'searchName' => 'search', 'clearUrl' => null, 'searchable' => true])
@php
    $query = collect($filters)->only(array_merge([$searchName], array_keys($fields)))
        ->filter(fn ($value) => is_scalar($value))->all();
    $activeQuery = collect($query)->filter(fn ($value, $name) => trim((string) $value) !== '' && ($name === $searchName || (string) $value !== (string) ($fields[$name]['neutral'] ?? '')))->all();
    $clearBase = $clearUrl ?: $action;
    $clearAllUrl = app(\App\Services\DocumentListFilterService::class)->withQuery($clearBase, $context);
@endphp
<div class="document-filter-summary" aria-label="{{ $label }} results and active filters">
    @if ($total !== null)
        <span class="document-filter-summary__count">{{ number_format($total) }} {{ $label }}{{ $activeQuery ? ' found' : '' }}</span>
    @elseif ($activeQuery)
        <span class="document-filter-summary__count">Active filters</span>
    @endif
    @if ($activeQuery)
        <ul class="document-filter-chips" aria-label="Active search and filters">
            @foreach ($activeQuery as $name => $value)
                @php
                    $field = $fields[$name] ?? ['label' => 'Search'];
                    $display = $field['options'][$value] ?? $value;
                    if (($field['type'] ?? null) === 'date') {
                        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
                        $display = $date ? $date->format('M j, Y') : $value;
                    }
                    $remaining = $context + \Illuminate\Support\Arr::except($query, $name);
                    if (array_key_exists('neutral', $field)) {
                        $remaining[$name] = $field['neutral'];
                    }
                    $removeUrl = app(\App\Services\DocumentListFilterService::class)->withQuery($action, $remaining);
                @endphp
                <li><a href="{{ $removeUrl }}" aria-label="Remove {{ $field['label'] }}: {{ $display }}" title="Remove this filter">
                    <span>{{ $field['label'] }}: {{ $display }}</span><span aria-hidden="true">&times;</span>
                </a></li>
            @endforeach
        </ul>
        <a class="document-filter-summary__clear" href="{{ $clearAllUrl }}">Clear all</a>
    @else
        <span class="document-filter-summary__hint">{{ $searchable ? 'Use search or Filters above to narrow this list.' : 'Use Filters above to narrow these results.' }}</span>
    @endif
</div>
