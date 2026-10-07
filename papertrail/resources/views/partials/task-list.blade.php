<div class="widget-heading">
    <div>
        <p class="eyebrow">Action Queue</p>
        <h2>Pending Tasks</h2>
    </div>
</div>

<ul class="task-list">
    @foreach ($tasks as $task)
        <li>
            <input type="checkbox" disabled>
            <span>{{ $task }}</span>
        </li>
    @endforeach
</ul>
