@if (! empty($adminStatistics) || ! empty($calendar))
    <section class="dashboard-support-widgets pt-smooth-enter" style="--pt-delay: 260ms" aria-label="Dashboard support widgets">
        @if (! empty($adminStatistics))
            <x-dashboard.admin-statistics :statistics="$adminStatistics" />
        @endif

        @if (! empty($calendar))
            <x-dashboard.compact-calendar :calendar="$calendar" />
        @endif
    </section>
@endif
