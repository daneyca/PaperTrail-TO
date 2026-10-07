@extends('layouts.dashboard')

@section('title', 'SVP Posted Records | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Posting Management</p>
            <h1>Posted Records</h1>
            <p>Review SVP posting records created by BACSEC-004 and posted for workflow continuation.</p>
        </div>

        <a href="{{ route('bac-secretariat.svp-posting.pending') }}" class="dashboard-action secondary-action">Pending Posting</a>
    </section>

    <section class="table-panel svp-table-panel" aria-label="SVP posted records">
        <x-document-filter-card :action="route('bac-secretariat.svp-posting.posted')" class="svp-filter-toolbar">
            <div class="user-search">
                <label for="posted-search">Search</label>
                <input id="posted-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="PR reference, title, office, or chain">
            </div>
            <div class="user-filter">
                <label for="posted-status">Status</label>
                <select id="posted-status" name="status">
                    <option value="all">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('bac-secretariat.svp-posting.posted') }}">Clear</a>
            </div>
        </x-document-filter-card>

        <div class="table-scroll">
            <table class="user-management-table svp-chain-table">
                <thead>
                    <tr>
                        <th>Posting Record</th>
                        <th>PR Reference</th>
                        <th>Requesting Office</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Posted</th>
                        <th>Workflow Continued</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($postingRecords as $postingRecord)
                        <tr>
                            <td><strong>{{ $postingRecord->displayNumber() }}</strong></td>
                            <td>{{ $postingRecord->pr_reference ?? 'N/A' }}</td>
                            <td>{{ $postingRecord->requesting_office ?? $postingRecord->sourcePrDocument?->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $postingRecord->approved_budget, 2) }}</td>
                            <td><span class="svp-status-badge {{ \Illuminate\Support\Str::slug($postingRecord->status) }}">{{ str($postingRecord->status)->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $postingRecord->posted_at?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                            <td>{{ $postingRecord->completed_at?->format('M d, Y h:i A') ?? 'Pending' }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-secretariat.svp-posting.show', $postingRecord) }}" title="View posting" aria-label="View posting">
                                        <x-papertrail.icon name="view" />
                                    </a>
                                    @if ($postingRecord->chain)
                                        <a href="{{ route('bac-secretariat.svp-monitoring.show', $postingRecord->chain) }}" title="View timeline" aria-label="View timeline">
                                            <x-papertrail.icon name="open" />
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <strong>No posted records yet</strong>
                                    <p>Posting records appear here after BACSEC-004 marks a sample posting as posted or closed.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($postingRecords->hasPages())
            <div class="pagination-wrap">
                {{ $postingRecords->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
