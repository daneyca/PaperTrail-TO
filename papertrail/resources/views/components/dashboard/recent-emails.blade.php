@props([
    'emails' => [],
])

@php
    $items = $emails['items'] ?? [];
@endphp

<article class="dashboard-support-card dashboard-email-card">
    <div class="support-widget-heading">
        <div>
            <p class="eyebrow">Email Activity</p>
            <h2>Recent Emails</h2>
        </div>
        <span class="support-icon-pill">
            <x-papertrail.icon name="mail" class="h-4 w-4" />
        </span>
    </div>

    <div class="recent-email-list">
        @forelse ($items as $email)
            <div class="recent-email-item">
                <div>
                    <strong>{{ $email['subject'] }}</strong>
                    <p>{{ $email['description'] }}</p>
                    <small>{{ $email['recipient'] ? 'Recipient: ' . $email['recipient'] . ' - ' : '' }}{{ $email['time'] }}</small>
                </div>
                <span class="email-status-badge email-status-{{ $email['tone'] }}">{{ $email['status'] }}</span>
            </div>
        @empty
            <div class="support-empty-state">
                <strong>No recent email.</strong>
            </div>
        @endforelse
    </div>
</article>
