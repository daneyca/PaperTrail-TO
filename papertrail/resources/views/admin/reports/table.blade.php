@extends('layouts.dashboard')

@section('title', $title . ' | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Reports</p>
            <h1>{{ $title }}</h1>
            <p>Review, search, export, or print this administrative report.</p>
        </div>

        <div class="report-hero-actions">
            <x-ui.action-button
                :href="route('admin.reports.export', ['type' => $type] + request()->query())"
                icon="download"
                label="Download Report"
                tooltip="Download Report"
                variant="download"
            />
            <x-ui.action-button
                :href="route('admin.reports.print', ['type' => $type] + request()->query())"
                icon="print"
                label="Print Report"
                tooltip="Print Report"
                variant="print"
                target="_blank"
                rel="noopener"
            />
        </div>
    </section>

    <section class="table-panel" aria-label="{{ $title }}">
        <form method="GET" action="{{ url()->current() }}" class="report-filter-toolbar">
            <div class="user-search">
                <label for="report-search">Search report</label>
                <input id="report-search" name="search" type="search" value="{{ request('search') }}" placeholder="Search by account, office, module, or action">
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ url()->current() }}">Clear</a>
            </div>
        </form>

        <div class="table-scroll">
            <table class="user-management-table report-table">
                <thead>
                    <tr>
                        @foreach ($headers as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            @if ($type === 'users')
                                <td class="nowrap">{{ $row->user_id }}</td>
                                <td>{{ $row->name }}</td>
                                <td>{{ $row->office }}</td>
                                <td>{{ $row->role }}</td>
                                <td><span class="status-pill status-{{ $row->status }}">{{ ucfirst($row->status) }}</span></td>
                                <td>{{ $row->created_at?->format('M d, Y') }}</td>
                                <td>{{ $row->updated_at?->format('M d, Y') }}</td>
                            @elseif ($type === 'offices')
                                <td class="nowrap">{{ $row->code }}</td>
                                <td>{{ $row->name }}</td>
                                <td>{{ $row->type }}</td>
                                <td><span class="status-pill status-{{ $row->status }}">{{ ucfirst($row->status) }}</span></td>
                                <td>{{ $row->users_count }}</td>
                                <td>{{ $row->created_at?->format('M d, Y') }}</td>
                            @elseif ($type === 'roles')
                                <td class="nowrap">{{ $row->code }}</td>
                                <td>{{ $row->name }}</td>
                                <td><span class="status-pill status-{{ $row->status }}">{{ ucfirst($row->status) }}</span></td>
                                <td>{{ $row->is_system ? 'System' : 'Custom' }}</td>
                                <td>{{ $row->users_count }}</td>
                                <td>{{ $row->permissions_count }}</td>
                                <td>{{ $row->created_at?->format('M d, Y') }}</td>
                            @else
                                <td class="nowrap">{{ $row->created_at?->format('M d, Y h:i A') }}</td>
                                <td class="nowrap">{{ $row->user_identifier ?? 'System' }}</td>
                                <td>{{ $row->user_name ?? 'System' }}</td>
                                <td>{{ $row->user_role ?? 'N/A' }}</td>
                                <td>{{ $row->user_office ?? 'N/A' }}</td>
                                <td>{{ $row->module }}</td>
                                <td>{{ $row->action }}</td>
                                <td><span class="severity-pill severity-{{ $row->severity }}">{{ ucfirst($row->severity) }}</span></td>
                                <td class="nowrap">{{ $row->ip_address }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($headers) }}">
                                <div class="empty-state">
                                    <strong>No report records found</strong>
                                    <p>Try adjusting the search text or clearing filters.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="pagination-wrap">
                {{ $rows->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
