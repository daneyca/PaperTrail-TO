@props(['action', 'filters' => [], 'fields' => [], 'label' => 'PPMPs', 'id' => 'ppmp',
    'context' => [], 'searchName' => 'search', 'showSearch' => true, 'clearUrl' => null,
    'note' => 'Leave a field set to “All” to include everything. Dates refer to the record’s last update.'])
@php
    $query = collect($filters)->only(array_merge([$searchName], array_keys($fields)))
        ->filter(fn ($value) => is_scalar($value))->all();
    $filterCount = collect($query)->except($searchName)->filter(fn ($value, $name) => trim((string) $value) !== '' && (string) $value !== (string) ($fields[$name]['neutral'] ?? ''))->count();
    $clearBase = $clearUrl ?: $action;
    $clearQuery = $context + (filled($query[$searchName] ?? null) ? [$searchName => $query[$searchName]] : []);
    $clearFiltersUrl = app(\App\Services\DocumentListFilterService::class)->withQuery($clearBase, $clearQuery);
@endphp

@if ($showSearch)
<form class="dashboard-search enterprise-topbar__search document-list-search" role="search" method="GET" action="{{ $action }}"
      data-list-search>
    <label class="sr-only" for="dashboard-search">Search {{ $label }}</label>
    <input id="dashboard-search" name="{{ $searchName }}" type="search" value="{{ $query[$searchName] ?? '' }}"
           placeholder="Search {{ $label }}..." aria-label="Search {{ $label }}"
           autocomplete="off">
    @foreach ($context + \Illuminate\Support\Arr::except($query, $searchName) as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <button class="document-list-search__submit" type="submit" aria-label="Search {{ $label }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/></svg>
    </button>
</form>
@endif
<button class="document-list-filter-toggle" type="button" data-open-list-filters="{{ $id }}-filter-dialog"
        aria-haspopup="dialog" aria-controls="{{ $id }}-filter-dialog" aria-expanded="false" title="Filter {{ $label }} on this page">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h18l-7 8v6l-4 2v-8Z"/></svg>
    <span>Filters</span>
    @if ($filterCount)<span class="document-list-filter-count" aria-label="{{ $filterCount }} active filters">{{ $filterCount }}</span>@endif
</button>

@push('modals')
    <dialog id="{{ $id }}-filter-dialog" class="document-list-filter-dialog" aria-labelledby="{{ $id }}-filter-title" aria-describedby="{{ $id }}-filter-help" data-list-filter-dialog>
        <div class="document-list-filter-dialog__heading">
            <div>
                <h2 id="{{ $id }}-filter-title">Filter {{ $label }}</h2>
                <p id="{{ $id }}-filter-help">Choose which {{ $label }} appear in this list.</p>
            </div>
            <button type="button" class="document-list-filter-close" data-close-list-filters aria-label="Close filters">&times;</button>
        </div>
        <form method="GET" action="{{ $action }}" data-list-filter-form>
            @if ($showSearch)
                <input type="hidden" name="{{ $searchName }}" value="{{ $query[$searchName] ?? '' }}" data-list-filter-search>
            @endif
            @foreach ($context as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <div class="document-list-filter-fields">
                @foreach ($fields as $name => $field)
                    @if (($field['type'] ?? null) === 'hidden')
                        <input type="hidden" name="{{ $name }}" value="{{ $query[$name] ?? '' }}">
                        @continue
                    @endif
                    <label for="{{ $id }}-filter-{{ $name }}">
                        <span>{{ $field['label'] }}</span>
                        @if (($field['type'] ?? 'select') !== 'select')
                            <input id="{{ $id }}-filter-{{ $name }}" type="{{ $field['type'] }}" name="{{ $name }}" value="{{ $query[$name] ?? '' }}"
                                @foreach (['min', 'max', 'step', 'placeholder', 'maxlength'] as $attribute)
                                    @if (isset($field[$attribute])) {{ $attribute }}="{{ $field[$attribute] }}" @endif
                                @endforeach>
                        @else
                            <select id="{{ $id }}-filter-{{ $name }}" name="{{ $name }}">
                                @if (! array_key_exists('empty', $field) || $field['empty'] !== null)
                                    <option value="">{{ $field['empty'] ?? 'Any' }}</option>
                                @endif
                                @foreach ($field['options'] as $value => $option)
                                    <option value="{{ $value }}" @selected((string) ($query[$name] ?? '') === (string) $value)
                                        @disabled(in_array((string) $value, $field['disabled_options'] ?? [], true))>{{ $option }}</option>
                                @endforeach
                            </select>
                        @endif
                    </label>
                @endforeach
            </div>
            @if (! $fields)
                <p class="document-list-filter-note">Use the search box above to find records. This list has no additional filters.</p>
            @else
                <p class="document-list-filter-note">{{ $note }}</p>
            @endif
            <div class="document-list-filter-actions">
                <a href="{{ $clearFiltersUrl }}">Clear filters</a>
                <button type="button" data-close-list-filters>Cancel</button>
                <button type="submit" class="document-list-filter-apply">Apply filters</button>
            </div>
        </form>
    </dialog>
@endpush

@once
    @push('page-styles')
        <link rel="stylesheet" href="{{ asset('css/document-list-controls.css') }}?v={{ filemtime(public_path('css/document-list-controls.css')) }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('js/document-list-controls.js') }}?v={{ filemtime(public_path('js/document-list-controls.js')) }}" defer></script>
    @endpush
@endonce
