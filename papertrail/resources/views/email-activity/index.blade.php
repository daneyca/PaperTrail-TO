@extends('layouts.dashboard')

@section('title', 'Email Activity | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero notifications-header">
        <div>
            <p class="eyebrow">Account Center</p>
            <h1>Email Activity</h1>
            <p>Review workflow email notifications sent, skipped, or failed by PaperTrail.</p>
        </div>
    </section>

    <section class="stat-grid stat-grid-modern notification-summary-strip" aria-label="Email activity summary">
        <x-dashboard.stat-card href="{{ route('email-activity.index') }}" value="{{ $counts['all'] }}" label="All Email Activity" accent="navy" />
        <x-dashboard.stat-card href="{{ route('email-activity.index', ['status' => 'sent']) }}" value="{{ $counts['sent'] }}" label="Sent" accent="green" />
        <x-dashboard.stat-card href="{{ route('email-activity.index', ['status' => 'failed']) }}" value="{{ $counts['failed'] }}" label="Failed" accent="red" />
        <x-dashboard.stat-card href="{{ route('email-activity.index', ['status' => 'skipped']) }}" value="{{ $counts['skipped'] }}" label="Skipped" accent="gold" />
    </section>

    <section class="table-panel notification-filter-panel" aria-label="Email activity filters">
        <form method="GET" action="{{ route('email-activity.index') }}" class="notification-filter-toolbar">
            <div class="user-search">
                <label for="email-activity-search">Search emails</label>
                <input id="email-activity-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Subject, recipient, tracking no.">
            </div>

            <div class="user-filter">
                <label for="email-status-filter">Status</label>
                <select id="email-status-filter" name="status">
                    <option value="">All statuses</option>
                    <option value="sent" @selected(($filters['status'] ?? '') === 'sent')>Sent</option>
                    <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Failed</option>
                    <option value="skipped" @selected(($filters['status'] ?? '') === 'skipped')>Skipped</option>
                </select>
            </div>

            <div class="user-filter">
                <label for="email-date-from">Date From</label>
                <input id="email-date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
            </div>

            <div class="user-filter">
                <label for="email-date-to">Date To</label>
                <input id="email-date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
            </div>

            <div class="filter-actions">
                <button type="submit">Apply</button>
                <a href="{{ route('email-activity.index') }}">Clear</a>
            </div>
        </form>
    </section>

    <section class="table-panel notification-list-panel" aria-label="Email activity list">
        <div class="notification-list">
            @forelse ($emails as $email)
                @php
                    $metadata = $email->metadata ?? [];
                    $subject = $metadata['subject'] ?? $email->target_label ?? $email->action;
                    $recipient = $metadata['recipient_user_id'] ?? $email->user_identifier ?? null;
                    $statusLabel = \App\Http\Controllers\EmailActivityController::statusLabel($email);
                    $statusType = \App\Http\Controllers\EmailActivityController::statusType($email);
                @endphp

                <article class="notification-row is-read">
                    <div class="notification-main">
                        <div class="notification-title-line">
                            <span class="notification-type type-{{ $statusType }}">{{ $statusLabel }}</span>
                            <span class="notification-module">Email Notifications</span>
                            @if ($email->tracking_number)
                                <span class="notification-module">{{ $email->tracking_number }}</span>
                            @endif
                        </div>

                        <h2>{{ $subject }}</h2>
                        <p>{{ $email->description ?: 'Email notification recorded.' }}</p>
                        <time datetime="{{ $email->created_at?->toIso8601String() }}">
                            {{ $recipient ? 'Recipient: ' . $recipient . ' - ' : '' }}{{ $email->created_at?->format('M d, Y h:i A') ?? 'Recently' }}
                        </time>
                    </div>
                </article>
            @empty
                <div class="empty-state">
                    <strong>No email activity yet</strong>
                    <p>Workflow email notifications will appear here once PaperTrail sends, skips, or records an email attempt.</p>
                </div>
            @endforelse
        </div>

        @if ($emails->hasPages())
            <div class="pagination-wrap">
                {{ $emails->appends(request()->query())->links('vendor.pagination.papertrail') }}
            </div>
        @endif
    </section>
@endsection
