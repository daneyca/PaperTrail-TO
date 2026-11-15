@props(['action', 'filters' => [], 'fields' => [], 'label' => 'PPMPs', 'total' => 0])
@php
    $query = collect($filters)->only(array_merge(['search'], array_keys($fields)))
        ->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')->all();
@endphp
<div class="document-filter-summary" aria-label="{{ $label }} results and active filters">
    <span class="document-filter-summary__count">{{ number_format($total) }} {{ $label }}{{ $query ? ' found' : '' }}</span>
    @if ($query)
        <ul class="document-filter-chips" aria-label="Active search and filters">
            @foreach ($query as $name => $value)
                @php
                    $field = $fields[$name] ?? ['label' => 'Search'];
                    $display = $field['options'][$value] ?? $value;
                    if (($field['type'] ?? null) === 'date') {
                        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
                        $display = $date ? $date->format('M j, Y') : $value;
                    }
                    $remaining = \Illuminate\Support\Arr::except($query, $name);
                    $removeUrl = $action . ($remaining ? '?' . http_build_query($remaining) : '');
                @endphp
                <li><a href="{{ $removeUrl }}" aria-label="Remove {{ $field['label'] }}: {{ $display }}" title="Remove this filter">
                    <span>{{ $field['label'] }}: {{ $display }}</span><span aria-hidden="true">&times;</span>
                </a></li>
            @endforeach
        </ul>
        <a class="document-filter-summary__clear" href="{{ $action }}">Clear all</a>
    @else
        <span class="document-filter-summary__hint">Use search or Filters above to narrow this list.</span>
    @endif
</div>
