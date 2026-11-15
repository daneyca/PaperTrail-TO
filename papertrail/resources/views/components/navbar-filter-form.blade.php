@props(['action', 'label' => null])
@php
    $definition = app(\App\Services\DocumentListFilterService::class)->describe((string) $slot, request()->query(), $action);
    $label = $label ?: trim(explode('|', $__env->yieldContent('title', 'Records'))[0]);
    $section = $definition['searchName'] ? 'topbar-search' : 'topbar-filters';
    $id = 'records-' . substr(sha1($action), 0, 10);
@endphp
@section($section)
    <x-document-list-controls :action="$action" :label="$label" :id="$id"
        :fields="$definition['fields']" :filters="$definition['values']" :context="$definition['context']"
        :search-name="$definition['searchName'] ?? 'search'" :show-search="$definition['searchName'] !== null"
        :clear-url="$definition['clearUrl']" note="Choose the options you need, then select Apply filters." />
@endsection
<x-document-filter-summary :action="$action" :label="$label" :total="null"
    :fields="$definition['fields']" :filters="$definition['values']" :context="$definition['context']"
    :search-name="$definition['searchName'] ?? 'search'" :clear-url="$action" :searchable="$definition['searchName'] !== null" />
