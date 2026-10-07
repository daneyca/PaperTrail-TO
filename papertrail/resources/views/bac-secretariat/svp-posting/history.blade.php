@extends('layouts.dashboard')

@section('title', 'SVP Posting History | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Posting Management</p>
            <h1>Posting History</h1>
            <p>Chronological record of SVP posting assignments, saved posting records, and completed posting tasks.</p>
        </div>

        <a href="{{ route('bac-secretariat.svp-posting.pending') }}" class="dashboard-action secondary-action">Pending Posting</a>
    </section>

    <section class="table-panel svp-table-panel" aria-label="SVP posting history">
        <x-document-filter-card :action="route('bac-secretariat.svp-posting.history')" class="svp-filter-toolbar">
            <div class="user-search">
                <label for="history-search">Search</label>
                <input id="history-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Action, status, chain, office, or remarks">
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('bac-secretariat.svp-posting.history') }}">Clear</a>
            </div>
        </x-document-filter-card>

        <ol class="svp-timeline svp-posting-history">
            @forelse ($events as $event)
                <li>
                    <time>{{ $event->created_at?->format('M d, Y h:i A') ?? 'No timestamp' }}</time>
                    <div>
                        <strong>{{ $event->action ?? 'Posting event recorded' }}</strong>
                        <p>
                            {{ $event->chain?->chain_number ?? $event->chain?->tracking_number ?? 'SVP Chain' }}
                            @if ($event->status)
                                <span class="svp-dot">.</span>
                                {{ str($event->status)->replace('_', ' ')->title() }}
                            @endif
                        </p>
                        <p>
                            {{ $event->performedBy?->name ?? 'System' }}
                            @if ($event->fromOffice || $event->toOffice)
                                <span class="svp-dot">.</span>
                                {{ $event->fromOffice?->name ?? 'N/A' }} to {{ $event->toOffice?->name ?? 'N/A' }}
                            @endif
                        </p>
                        @if ($event->remarks)
                            <p class="svp-event-remarks">{{ $event->remarks }}</p>
                        @endif
                    </div>
                </li>
            @empty
                <li>
                    <time>Pending</time>
                    <div>
                        <strong>No posting history yet</strong>
                        <p>Posting assignment and completion events will appear here.</p>
                    </div>
                </li>
            @endforelse
        </ol>

        @if ($events->hasPages())
            <div class="pagination-wrap">
                {{ $events->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
