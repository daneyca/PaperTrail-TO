@extends('layouts.dashboard')

@section('title', 'Search | PaperTrail')

@section('content')
    <section class="global-search-page">
        <div class="global-search-heading">
            <div>
                <span class="global-search-eyebrow">RECORDS</span>
                <h1>Search Results</h1>
                @if (mb_strlen($term) >= 2)
                    <p>Results for <strong>{{ $term }}</strong></p>
                @endif
            </div>
            <span class="global-search-count">{{ $results->count() }} {{ \Illuminate\Support\Str::plural('result', $results->count()) }}</span>
        </div>

        @if (mb_strlen($term) < 2)
            <p class="global-search-empty">Enter at least 2 characters to search your records.</p>
        @elseif ($results->isEmpty())
            <p class="global-search-empty">No matching records found.</p>
        @else
            <div class="global-search-results">
                @foreach ($results as $result)
                    <a class="global-search-result" href="{{ $result['url'] }}">
                        <span class="global-search-result__type">{{ $result['type'] }}</span>
                        <span class="global-search-result__main">
                            <strong>{{ $result['reference'] }}</strong>
                            <span>{{ $result['title'] }}</span>
                        </span>
                        <span class="global-search-result__status">{{ str_replace('_', ' ', $result['status']) }}</span>
                    </a>
                @endforeach
            </div>
            @if ($results->count() === 30)
                <p class="global-search-limit">Showing the 30 most recently updated matches. Refine your search to find a specific record.</p>
            @endif
        @endif
    </section>
@endsection
