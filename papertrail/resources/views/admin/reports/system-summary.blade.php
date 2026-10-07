@extends('layouts.dashboard')

@section('title', 'System Summary Report | PaperTrail')

@section('content')
    <section class="dashboard-hero admin-users-hero">
        <div>
            <p class="eyebrow">Reports</p>
            <h1>System Summary Report</h1>
            <p>{{ $lguName }} administrative summary for current PaperTrail records.</p>
        </div>

        <div class="report-hero-actions">
            <x-ui.action-button
                :href="route('admin.reports.export', 'system-summary')"
                icon="download"
                label="Download Report"
                tooltip="Download Report"
                variant="download"
            />
            <x-ui.action-button
                :href="route('admin.reports.print', 'system-summary')"
                icon="print"
                label="Print Report"
                tooltip="Print Report"
                variant="print"
                target="_blank"
                rel="noopener"
            />
        </div>
    </section>

    <section class="summary-grid" aria-label="System summary counts">
        @foreach ($summary as $label => $value)
            <article class="summary-card">
                <span>{{ $value }}</span>
                <p>{{ $label }}</p>
            </article>
        @endforeach
    </section>

    <section class="dashboard-widget-grid">
        <article class="dashboard-widget">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Audit Activity</p>
                    <h2>Most Active Modules</h2>
                </div>
            </div>

            <ul class="activity-list report-list">
                @forelse ($modules as $module)
                    <li><span></span><p><strong>{{ $module->module ?? 'System' }}</strong> recorded {{ $module->total }} audit event{{ $module->total === 1 ? '' : 's' }}.</p></li>
                @empty
                    <li><span></span><p>No audit activity has been recorded yet.</p></li>
                @endforelse
            </ul>
        </article>

        <article class="dashboard-widget">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Recent Activity</p>
                    <h2>Latest Audit Events</h2>
                </div>
            </div>

            <ul class="activity-list report-list">
                @forelse ($activities as $activity)
                    <li><span></span><p><strong>{{ $activity->action }}</strong> in {{ $activity->module }} by {{ $activity->user_name ?? 'System' }} on {{ $activity->created_at?->format('M d, Y h:i A') }}.</p></li>
                @empty
                    <li><span></span><p>No recent activity is available.</p></li>
                @endforelse
            </ul>
        </article>
    </section>
@endsection
