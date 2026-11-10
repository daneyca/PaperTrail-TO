@extends('layouts.dashboard')

@section('title', 'Status Tracking | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div><p class="eyebrow">{{ $eyebrow }}</p><h1>Status Tracking</h1><p>Track documents you created, are assigned to, or have processed.</p></div>
    </section>

    <section class="table-panel" aria-label="Document status tracking">
        <x-document-filter-card :action="route('status-tracking.index')">
            <div class="user-search"><label for="tracking-search">Search</label><input id="tracking-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Document number or title"></div>
            <div class="user-filter"><label for="tracking-type">Document type</label><select id="tracking-type" name="type"><option value="">All types</option>@foreach ($types as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="tracking-status">Status</label><select id="tracking-status" name="status"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
            <div class="user-filter"><label for="tracking-from">Updated from</label><input id="tracking-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="user-filter"><label for="tracking-to">Updated to</label><input id="tracking-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="filter-actions"><button type="submit">Apply</button><a href="{{ route('status-tracking.index') }}">Clear</a></div>
        </x-document-filter-card>
        <div class="section-heading"><div><p class="eyebrow">Document Tracking</p><h2>Tracked Documents</h2></div><span class="muted-text">{{ $documents->total() }} {{ \Illuminate\Support\Str::plural('document', $documents->total()) }}</span></div>
        <div class="table-scroll">
            <table class="user-management-table" style="min-width: 1040px; width: 100%">
                <thead><tr><th>Document</th><th>Type</th><th>Current stage</th><th>Current status</th><th>Current location / handler</th><th>Last updated</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php
                            $url = route('status-tracking.show', ['type' => $document->tracking_type, 'document' => $document->document_id]);
                            $statusClass = str_contains($document->status ?? '', 'returned') ? 'returned' : (in_array($document->status, ['approved', 'accepted', 'completed', 'fully_signed', 'closed', 'posted'], true) ? 'completed' : (str_contains($document->status ?? '', 'draft') ? 'draft' : 'in-progress'));
                        @endphp
                        <tr>
                            <td><a href="{{ $url }}" class="svp-tracking-title-link"><strong>{{ $document->reference }}</strong></a>@if ($document->title !== $document->reference)<br><span class="muted-text">{{ \Illuminate\Support\Str::limit($document->title, 85) }}</span>@endif</td>
                            <td>{{ $document->document_type }}</td>
                            <td>{{ $document->stage }}</td>
                            <td><span class="svp-status-badge {{ $statusClass }}">{{ str($document->status ?: 'Not recorded')->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $document->location }}@if ($document->handler)<br><span class="muted-text">Handler: {{ $document->handler }}</span>@endif</td>
                            <td>{{ $document->updated_at ? \Illuminate\Support\Carbon::parse($document->updated_at)->format('M d, Y') : 'Not recorded' }}@if ($document->updated_at)<br><span class="muted-text">{{ \Illuminate\Support\Carbon::parse($document->updated_at)->format('h:i A') }}</span>@endif</td>
                            <td><a href="{{ $url }}" class="dashboard-action secondary-action">Track</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state"><strong>No tracked documents found</strong><p>Documents you prepare, receive, review, or sign will appear here when available. Try clearing the filters.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($documents->hasPages())<div class="pagination-wrap">{{ $documents->links('vendor.pagination.papertrail') }}</div>@endif
    </section>
@endsection
