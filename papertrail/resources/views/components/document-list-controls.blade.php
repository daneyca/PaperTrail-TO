@props(['action', 'filters' => [], 'fields' => [], 'label' => 'PPMPs', 'id' => 'ppmp'])
@php
    $query = collect($filters)->only(array_merge(['search'], array_keys($fields)))
        ->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')->all();
    $filterCount = count(\Illuminate\Support\Arr::except($query, 'search'));
    $clearUrl = $action . (filled($query['search'] ?? null) ? '?' . http_build_query(['search' => $query['search']]) : '');
@endphp

<form class="dashboard-search enterprise-topbar__search document-list-search" role="search" method="GET" action="{{ $action }}"
      data-list-search>
    <label class="sr-only" for="dashboard-search">Search {{ $label }} by tracking number or title</label>
    <input id="dashboard-search" name="search" type="search" value="{{ $query['search'] ?? '' }}"
           placeholder="Search {{ $label }}..." aria-label="Search {{ $label }} by tracking number or title"
           autocomplete="off">
    @foreach (\Illuminate\Support\Arr::except($query, 'search') as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <button class="document-list-search__submit" type="submit" aria-label="Search {{ $label }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/></svg>
    </button>
</form>
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
            <input type="hidden" name="search" value="{{ $query['search'] ?? '' }}" data-list-filter-search>
            <div class="document-list-filter-fields">
                @foreach ($fields as $name => $field)
                    <label for="{{ $id }}-filter-{{ $name }}">
                        <span>{{ $field['label'] }}</span>
                        @if (($field['type'] ?? 'select') === 'date')
                            <input id="{{ $id }}-filter-{{ $name }}" type="date" name="{{ $name }}" value="{{ $query[$name] ?? '' }}">
                        @else
                            <select id="{{ $id }}-filter-{{ $name }}" name="{{ $name }}">
                                <option value="">{{ $field['empty'] ?? 'Any' }}</option>
                                @foreach ($field['options'] as $value => $option)
                                    <option value="{{ $value }}" @selected((string) ($query[$name] ?? '') === (string) $value)>{{ $option }}</option>
                                @endforeach
                            </select>
                        @endif
                    </label>
                @endforeach
            </div>
            <p class="document-list-filter-note">Leave a field set to “All” to include everything. Dates refer to the record’s last update.</p>
            <div class="document-list-filter-actions">
                <a href="{{ $clearUrl }}">Clear filters</a>
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
