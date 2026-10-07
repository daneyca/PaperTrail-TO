@extends('layouts.dashboard')

@section('title', 'Pending SVP Posting | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Posting Management</p>
            <h1>Pending Posting</h1>
            <p>Review SVP transactions above PHP 50,000 and below PHP 200,000 assigned to BACSEC-004 after BAC Resolution completion.</p>
        </div>

        <a href="{{ route('bac-secretariat.svp-posting.create') }}" class="dashboard-action">Create Posting</a>
    </section>

    <section class="table-panel svp-table-panel" aria-label="Pending SVP posting tasks">
        <x-document-filter-card :action="route('bac-secretariat.svp-posting.pending')" class="svp-filter-toolbar">
            <div class="user-search">
                <label for="posting-search">Search</label>
                <input id="posting-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="PR number, SVP chain, office, or title">
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('bac-secretariat.svp-posting.pending') }}">Clear</a>
            </div>
        </x-document-filter-card>

        <div class="table-scroll">
            <table class="user-management-table svp-chain-table">
                <thead>
                    <tr>
                        <th>SVP Chain</th>
                        <th>PR Reference</th>
                        <th>Requesting Office</th>
                        <th>Amount</th>
                        <th>Posting Status</th>
                        <th>Assigned</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($chains as $chain)
                        @php
                            $pr = $chain->sourcePrDocument;
                            $posting = $chain->latestPostingRecord;
                        @endphp
                        <tr>
                            <td><strong>{{ $chain->chain_number ?? 'SVP Chain #'.$chain->id }}</strong></td>
                            <td>{{ $pr?->pr_no ?? $pr?->tracking_number ?? $chain->tracking_number ?? 'N/A' }}</td>
                            <td>{{ $chain->office_name ?? $pr?->submittingOffice?->name ?? 'N/A' }}</td>
                            <td class="nowrap">PHP {{ number_format((float) $chain->total_amount, 2) }}</td>
                            <td>
                                @php
                                    $postingStatus = $posting?->status ?? \App\Models\SvpPostingRecord::STATUS_PENDING_POSTING;
                                @endphp
                                <span class="svp-status-badge {{ \Illuminate\Support\Str::slug($postingStatus) }}">
                                    {{ str($postingStatus)->replace('_', ' ')->title() }}
                                </span>
                            </td>
                            <td>{{ $pr?->assignedTo?->name ?? 'BACSEC-004' }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('bac-secretariat.svp-posting.create', $chain) }}" title="Open Posting" aria-label="Open Posting">
                                        <x-papertrail.icon name="edit" />
                                    </a>
                                    <a href="{{ route('bac-secretariat.svp-monitoring.show', $chain) }}" title="View timeline" aria-label="View timeline">
                                        <x-papertrail.icon name="view" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <strong>No pending posting tasks</strong>
                                    <p>SVP transactions requiring posting will appear here after BAC Resolution completion.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($chains->hasPages())
            <div class="pagination-wrap">
                {{ $chains->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
