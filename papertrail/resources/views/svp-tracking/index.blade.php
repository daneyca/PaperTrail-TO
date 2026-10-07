@extends('layouts.dashboard')

@section('title', $title.' | PaperTrail')

@section('content')
    @php
        $isHeadOfficeStatusTracking = ($context ?? null) === 'head-office' && $title === 'Status Tracking';
        $svpColumnCount = $isHeadOfficeStatusTracking ? 7 : 9;
    @endphp

    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">{{ $eyebrow }}</p>
            <h1>{{ $title }}</h1>
            <p>{{ $subtitle }}</p>
        </div>
    </section>

    <section class="table-panel svp-table-panel {{ $isHeadOfficeStatusTracking ? 'svp-table-panel--compact' : '' }}" aria-label="{{ $title }} list">
        <x-document-filter-card :action="route($indexRoute)" class="svp-filter-toolbar">
            <div class="user-search">
                <label for="svp-search">Search</label>
                <input id="svp-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="SVP chain, PR tracking number, title, or office">
            </div>

            @if ($showOfficeFilter ?? false)
                <div class="user-filter">
                    <label for="office-filter">Office</label>
                    <select id="office-filter" name="office_id">
                        <option value="">All offices</option>
                        @foreach ($offices ?? [] as $office)
                            <option value="{{ $office->id }}" @selected((string) ($filters['office_id'] ?? '') === (string) $office->id)>{{ $office->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="user-filter">
                <label for="fiscal-year-filter">Fiscal Year</label>
                <select id="fiscal-year-filter" name="fiscal_year">
                    <option value="">All years</option>
                    @foreach ($fiscalYears as $year)
                        <option value="{{ $year }}" @selected((string) ($filters['fiscal_year'] ?? '') === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="stage-filter">Current Stage</label>
                <select id="stage-filter" name="stage">
                    <option value="">All stages</option>
                    @foreach ($stages as $stage)
                        <option value="{{ $stage }}" @selected(($filters['stage'] ?? '') === $stage)>{{ $stage }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="status-filter">Status</label>
                <select id="status-filter" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="user-filter">
                <label for="date-from">Date From</label>
                <input id="date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
            </div>

            <div class="user-filter">
                <label for="date-to">Date To</label>
                <input id="date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route($indexRoute) }}">Clear</a>
            </div>
        </x-document-filter-card>

        <div class="section-heading">
            <div>
                <p class="eyebrow">{{ $title === 'Status Tracking' ? 'Document Tracking' : 'SVP / Alternative Procurement' }}</p>
                <h2>{{ $title === 'Status Tracking' ? 'Tracked Documents' : 'SVP Chain Status' }}</h2>
            </div>
        </div>

        <div class="table-scroll">
            <table class="user-management-table svp-chain-table {{ $isHeadOfficeStatusTracking ? 'svp-chain-table--compact' : '' }}">
                <colgroup>
                    <col class="svp-col-chain">
                    <col class="svp-col-pr">
                    @unless ($isHeadOfficeStatusTracking)
                        <col class="svp-col-office">
                    @endunless
                    <col class="svp-col-stage">
                    <col class="svp-col-status">
                    <col class="svp-col-location">
                    @unless ($isHeadOfficeStatusTracking)
                        <col class="svp-col-amount">
                    @endunless
                    <col class="svp-col-updated">
                    <col class="svp-col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th>{{ $title === 'Status Tracking' ? 'Document' : 'SVP Chain No.' }}</th>
                        <th>PR Tracking No.</th>
                        @unless ($isHeadOfficeStatusTracking)
                            <th>Requesting Office</th>
                        @endunless
                        <th>Current Stage</th>
                        <th>Current Status</th>
                        <th>Current Location</th>
                        @unless ($isHeadOfficeStatusTracking)
                            <th>Total Amount</th>
                        @endunless
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($chains as $chain)
                        @php
                            $pr = $chain->sourcePrDocument;
                            $latestUrl = null;
                            $trackingUrl = route($showRoute, $chain);
                            $trackingTitle = $chain->chain_number ?? 'SVP Chain #'.$chain->id;
                            $currentLocation = $pr?->currentOffice?->name
                                ?? $chain->office?->name
                                ?? $chain->office_name
                                ?? 'N/A';
                            $currentHandler = $pr?->assignedTo?->name;

                            foreach (array_reverse(app(\App\Services\SvpChainService::class)->documentCards($chain, $context)) as $card) {
                                if ($card['view_url']) {
                                    $latestUrl = $card['view_url'];
                                    break;
                                }
                            }
                        @endphp
                        <tr class="svp-tracking-row">
                            <td class="nowrap">
                                <a href="{{ $trackingUrl }}" class="svp-tracking-title-link">
                                    <strong>{{ $trackingTitle }}</strong>
                                </a>
                            </td>
                            <td class="nowrap">
                                <span class="svp-tracking-link svp-tracking-link--muted">
                                    {{ $pr?->displayNumber() ?? $chain->tracking_number ?? 'N/A' }}
                                </span>
                                @if ($pr?->pr_no)
                                    <br><span class="muted-text">Official PR: {{ $pr->pr_no }}</span>
                                @endif
                            </td>
                            @unless ($isHeadOfficeStatusTracking)
                                <td>{{ $chain->office_name ?? $pr?->submittingOffice?->name ?? 'N/A' }}</td>
                            @endunless
                            <td>{{ $chain->current_stage ?? 'Purchase Request' }}</td>
                            <td><span class="svp-status-badge {{ \Illuminate\Support\Str::slug($chain->current_status ?? 'pending') }}">{{ str($chain->current_status ?? 'pending')->replace('_', ' ')->title() }}</span></td>
                            <td>
                                {{ $currentLocation }}
                                @if ($currentHandler)
                                    <br><span class="muted-text">Handler: {{ $currentHandler }}</span>
                                @endif
                            </td>
                            @unless ($isHeadOfficeStatusTracking)
                                <td class="nowrap">PHP {{ number_format((float) $chain->total_amount, 2) }}</td>
                            @endunless
                            <td class="nowrap">{{ $chain->updated_at?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                            <td>
                                <div class="table-actions">
                                    <a
                                        href="{{ $trackingUrl }}"
                                        class="svp-table-text-action svp-table-text-action--primary"
                                        title="Track document movement"
                                        aria-label="Track document movement for {{ $trackingTitle }}"
                                    >
                                        Track
                                    </a>
                                    @if ($pr && $context === 'head-office' && Route::has('head-office.pr.show'))
                                        <a href="{{ route('head-office.pr.show', $pr) }}" title="View PR" aria-label="View PR">
                                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path d="M6.75 3.75h7.5L18 7.5v12.75H6.75Z" />
                                                <path d="M14.25 3.75V7.5H18" />
                                                <path d="M8.75 12h6.5M8.75 15h6.5" />
                                            </svg>
                                        </a>
                                    @elseif ($pr && $context === 'bac-secretariat' && Route::has('bac-secretariat.pr.show'))
                                        <a href="{{ route('bac-secretariat.pr.show', $pr) }}" title="View PR" aria-label="View PR">
                                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path d="M6.75 3.75h7.5L18 7.5v12.75H6.75Z" />
                                                <path d="M14.25 3.75V7.5H18" />
                                                <path d="M8.75 12h6.5M8.75 15h6.5" />
                                            </svg>
                                        </a>
                                    @endif
                                    @if ($latestUrl)
                                        <a href="{{ $latestUrl }}" title="Open latest document" aria-label="Open latest document">
                                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path d="M7 17 17 7" />
                                                <path d="M9 7h8v8" />
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        @if ($isHeadOfficeStatusTracking)
                            <tr class="svp-tracking-row svp-tracking-row--mock">
                                <td class="nowrap">
                                    <a href="{{ route('head-office.svp-tracking.mock-preview') }}" class="svp-tracking-title-link">
                                        <strong>MOCK-PPMP-2026-0001</strong>
                                    </a>
                                    <br><span class="svp-mock-label">Mock only</span>
                                </td>
                                <td class="nowrap"><span class="svp-tracking-link svp-tracking-link--muted">PPMP-2026-MOCK-0001</span></td>
                                <td>Under APP Consolidation</td>
                                <td><span class="svp-status-badge in-progress">Mock In Progress</span></td>
                                <td>
                                    BAC Secretariat
                                    <br><span class="muted-text">Handler: BACSEC-004 Demo User</span>
                                </td>
                                <td class="nowrap">Oct 05, 2026 03:58 PM</td>
                                <td>
                                    <div class="svp-mock-actions">
                                        <a
                                            href="{{ route('head-office.svp-tracking.mock-preview') }}"
                                            class="svp-mock-action"
                                        >
                                            Track
                                        </a>
                                        <button
                                            type="button"
                                            class="svp-mock-action svp-mock-action--danger"
                                            disabled
                                            title="Mock record only"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @else
                            <tr>
                                <td colspan="{{ $svpColumnCount }}">
                                    <div class="empty-state">
                                        <strong>No SVP records yet</strong>
                                        <p>SVP transactions will appear here once Purchase Requests are created or submitted.</p>
                                    </div>
                                </td>
                            </tr>
                        @endif
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
