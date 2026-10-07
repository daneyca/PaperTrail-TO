<div class="widget-heading">
    <div>
        <p class="eyebrow">Updates</p>
        <h2>Recent Activities</h2>
    </div>
</div>

<ul class="activity-list">
    @foreach ($activities as $activity)
        <li>
            <span></span>
            <p>{{ $activity }}</p>
        </li>
    @endforeach
</ul>
