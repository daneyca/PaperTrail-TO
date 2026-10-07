@extends('layouts.dashboard')

@section('title', $notification->title . ' | Notifications')

@section('content')
    <section class="dashboard-hero admin-users-hero notifications-header">
        <div>
            <p class="eyebrow">Notification Detail</p>
            <h1>{{ $notification->title }}</h1>
            <p>{{ $notification->module ?? 'System' }} update received {{ $notification->created_at?->diffForHumans() }}.</p>
        </div>

        <a href="{{ route('notifications.index') }}" class="dashboard-action secondary-action">Back to Notifications</a>
    </section>

    <section class="budget-review-layout reviewed-detail-layout">
        <article class="table-panel notification-detail-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">{{ $notification->module ?? 'System' }}</p>
                    <h2>{{ $notification->title }}</h2>
                    <p>{{ $notification->message }}</p>
                </div>
                <span class="notification-type type-{{ $notification->type }}">{{ ucfirst($notification->type) }}</span>
            </div>

            <div class="detail-grid budget-detail-grid">
                <div><span>Type</span><strong>{{ ucfirst($notification->type) }}</strong></div>
                <div><span>Module</span><strong>{{ $notification->module ?? 'System' }}</strong></div>
                <div><span>Date / Time</span><strong>{{ $notification->created_at?->format('M d, Y h:i A') }}</strong></div>
                <div><span>Read Status</span><strong>{{ $notification->read_at ? 'Read on ' . $notification->read_at->format('M d, Y h:i A') : 'Unread' }}</strong></div>
                <div><span>Related Type</span><strong>{{ $notification->related_type ? class_basename($notification->related_type) : 'N/A' }}</strong></div>
                <div><span>Related ID</span><strong>{{ $notification->related_id ?? 'N/A' }}</strong></div>
            </div>
        </article>

        <aside class="table-panel budget-action-panel reviewed-summary-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Actions</p>
                    <h2>Notification Options</h2>
                </div>
            </div>

            <div class="notification-detail-actions">
                @if ($notification->action_url)
                    <a href="{{ $notification->action_url }}" class="dashboard-action">Open Related Page</a>
                @endif

                <form method="POST" action="{{ route('notifications.destroy', $notification) }}" onsubmit="return confirm('Delete this notification?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="dashboard-action secondary-action">Delete Notification</button>
                </form>
            </div>
        </aside>
    </section>
@endsection
