@props([
    'statistics' => [],
])

@php
    $kpis = $statistics['kpis'] ?? [];
    $documentFlow = $statistics['documentFlow'] ?? [];
    $recentActivity = $statistics['recentActivity'] ?? [];
@endphp

<article class="dashboard-support-card dashboard-admin-statistics">
    <div class="support-widget-heading">
        <div>
            <p class="eyebrow">Administration</p>
            <h2>System Statistics</h2>
        </div>
        <span class="support-icon-pill">
            <x-papertrail.icon name="spark" class="h-4 w-4" />
        </span>
    </div>

    <div class="admin-stat-kpi-grid">
        @foreach ($kpis as $kpi)
            @if (! empty($kpi['href']))
                <a href="{{ $kpi['href'] }}" class="admin-stat-kpi admin-stat-{{ $kpi['tone'] ?? 'blue' }}">
                    <strong>{{ number_format((int) ($kpi['value'] ?? 0)) }}</strong>
                    <span>{{ $kpi['label'] }}</span>
                </a>
            @else
                <div class="admin-stat-kpi admin-stat-{{ $kpi['tone'] ?? 'blue' }}">
                    <strong>{{ number_format((int) ($kpi['value'] ?? 0)) }}</strong>
                    <span>{{ $kpi['label'] }}</span>
                </div>
            @endif
        @endforeach
    </div>

    <div class="admin-stat-detail-grid">
        <div class="admin-flow-list">
            <div class="support-subheading">
                <span>Document Status Mix</span>
            </div>

            @foreach ($documentFlow as $flow)
                <div class="admin-flow-row">
                    <div>
                        <span>{{ $flow['label'] }}</span>
                        <strong>{{ number_format((int) ($flow['value'] ?? 0)) }}</strong>
                    </div>
                    <div class="admin-flow-bar admin-flow-{{ $flow['tone'] ?? 'blue' }}">
                        <span style="width: {{ (int) ($flow['percentage'] ?? 0) }}%"></span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="admin-activity-list">
            <div class="support-subheading">
                <span>Recent Activity</span>
            </div>

            @forelse ($recentActivity as $activity)
                <div class="admin-activity-item">
                    <strong>{{ $activity['action'] }}</strong>
                    <small>{{ $activity['module'] }} · {{ $activity['time'] }}</small>
                </div>
            @empty
                <p class="support-empty-text">No recent administrative activity.</p>
            @endforelse
        </div>
    </div>
</article>
