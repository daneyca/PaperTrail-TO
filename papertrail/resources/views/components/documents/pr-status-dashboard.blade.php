@props(['tracking'])

@php
    $date = fn ($value, $format = 'M d, Y, h:i A') => $value ? \Illuminate\Support\Carbon::parse($value)->format($format) : 'Not recorded';
@endphp

<section class="pr-status-summary pr-status-panel" aria-label="Purchase Request summary">
    <div class="pr-status-summary-document">
        <span class="pr-status-summary-icon"><x-papertrail.icon name="document" /></span>
        <div><span class="pr-status-label">PR No.</span><strong class="pr-status-number">{{ $tracking['number'] }}</strong><span class="pr-status-badge is-{{ $tracking['state'] }}">{{ $tracking['overall'] }}</span></div>
    </div>
    <div><span class="pr-status-label">Requesting office</span><strong>{{ $tracking['office'] }}</strong></div>
    <div><span class="pr-status-label">Date created</span><strong>{{ $date($tracking['created'], 'M d, Y') }}<span class="pr-status-created-time">{{ $tracking['created'] ? $date($tracking['created'], 'h:i A') : '' }}</span></strong></div>
    <div class="pr-status-progress"><span class="pr-status-label">PR submission progress</span><strong>{{ $tracking['completed'] }} of {{ $tracking['steps']->count() }} steps <span>{{ $tracking['percent'] }}%</span></strong><progress value="{{ $tracking['completed'] }}" max="{{ $tracking['steps']->count() }}" aria-label="Completed PR submission steps">{{ $tracking['percent'] }}%</progress></div>
</section>

<ol class="pr-status-stepper" aria-label="Purchase Request workflow">
    @foreach ($tracking['steps'] as $step)
        <li class="pr-status-step is-{{ $step['state'] }}" @if (in_array($step['state'], ['current', 'returned'])) aria-current="step" @endif>
            <span class="pr-status-step-icon"><x-papertrail.icon :name="$step['state'] === 'completed' ? 'check' : $step['icon']" /></span>
            <div class="pr-status-step-card"><strong>{{ $step['label'] }}</strong><span class="pr-status-badge is-{{ $step['state'] }}">{{ $step['status'] }}</span></div>
        </li>
    @endforeach
</ol>

<div class="pr-status-columns">
    <section class="pr-status-activity pr-status-panel" aria-labelledby="pr-status-activity-title" data-pr-activity>
        <header class="pr-status-panel-heading">
            <h2 id="pr-status-activity-title"><x-papertrail.icon name="clock" />Activity Timeline</h2>
            <label class="sr-only" for="pr-activity-filter">Filter activities</label>
            <select id="pr-activity-filter" data-pr-activity-filter><option value="all">All activities</option><option value="completed">Past updates</option><option value="current">Latest update</option><option value="pending">Upcoming</option></select>
        </header>
        <ol class="pr-status-events">
            @forelse ($tracking['activities'] as $activity)
                <li class="pr-status-event is-{{ $activity['state'] }}" data-pr-activity-state="{{ in_array($activity['state'], ['current', 'returned']) ? 'current' : $activity['state'] }}">
                    <span class="pr-status-event-marker" aria-hidden="true">@if ($activity['state'] === 'completed')<x-papertrail.icon name="check" />@endif</span>
                    <details class="pr-status-event-card">
                        <summary><span class="pr-status-event-heading"><strong>{{ $activity['title'] }}</strong><span class="pr-status-event-meta">{{ $date($activity['date']) }} <span aria-hidden="true">&middot;</span> By: {{ $activity['actor'] }}</span></span><span class="pr-status-badge is-{{ $activity['state'] }}">{{ $activity['status'] }}</span><span class="pr-status-chevron" aria-hidden="true"></span></summary>
                        <div class="pr-status-event-details"><dl><div><dt>From</dt><dd>{{ $activity['from'] }}</dd></div><div><dt>To</dt><dd>{{ $activity['to'] }}</dd></div><div><dt>Status</dt><dd>{{ $activity['before'] }} &rarr; {{ $activity['after'] }}</dd></div></dl>@if ($activity['comments'])<p>{{ $activity['comments'] }}</p>@endif</div>
                    </details>
                </li>
            @empty
                <li class="pr-status-no-history">No routing history has been recorded yet. New movements will appear here.</li>
            @endforelse
            @if ($tracking['has_next'])
                <li class="pr-status-event is-pending" data-pr-activity-state="pending"><span class="pr-status-event-marker" aria-hidden="true"></span><div class="pr-status-event-card pr-status-upcoming"><div><strong>{{ $tracking['handler'] ?? $tracking['location'] }}</strong><p>{{ $tracking['next'] }}</p></div><span class="pr-status-badge is-pending">Next action</span></div></li>
            @endif
        </ol>
        <p class="pr-status-filter-empty" data-pr-activity-empty role="status" hidden>No activities match this filter.</p>
        <p class="pr-status-updated">Last updated: {{ $date($tracking['updated']) }}. Expand an activity to see routing details.</p>
    </section>

    <aside class="pr-status-sidebar" aria-label="Document route and next action">
        <section class="pr-status-route pr-status-panel" aria-labelledby="pr-status-route-title">
            <header class="pr-status-panel-heading"><h2 id="pr-status-route-title"><x-papertrail.icon name="users" />Document Route</h2></header>
            <ol>@foreach ($tracking['route'] as $office)<li class="{{ $loop->last ? 'is-current' : 'is-completed' }}"><span class="pr-status-route-marker" aria-hidden="true">@unless ($loop->last)<x-papertrail.icon name="check" />@endunless</span><strong>{{ $office }}</strong>@if ($loop->last)<span class="pr-status-badge is-current">Current</span>@endif</li>@endforeach</ol>
            @if ($tracking['handler'])<div class="pr-status-handler"><span class="pr-status-label">Current handler</span><strong>{{ $tracking['handler'] }}</strong></div>@endif
        </section>
        <section class="pr-status-next pr-status-panel" aria-labelledby="pr-status-next-title">
            <h2 id="pr-status-next-title"><x-papertrail.icon name="open" />Next Action</h2>
            <p>{{ $tracking['next'] }}</p>
            @if ($tracking['handler'])<div class="pr-status-next-person">For action by <strong>{{ $tracking['handler'] }}</strong></div>@endif
            <div class="pr-status-current-stage"><span class="pr-status-label">Current stage</span><strong>{{ $tracking['stage'] }}</strong></div>
        </section>
    </aside>
</div>
