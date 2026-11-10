<div class="observer-schedule" data-observer-schedule>
    <div class="observer-schedule__heading">
        <span aria-hidden="true">-</span>
        @include('bac-secretariat.competitive-bidding.observers.field', ['path' => $path.'.when', 'value' => $schedule['when'] ?? '', 'label' => 'Schedule date and time', 'placeholder' => 'Enter date and time, e.g. January 23, 2027; 10:00 in the morning'])
        @if ($editable ?? false)
            <button type="button" class="observer-row-remove no-print" data-action-icon="false" data-observer-remove="schedule">Remove schedule</button>
        @endif
    </div>
    <ol class="observer-projects" data-observer-projects>
        @foreach ($schedule['projects'] ?? [] as $projectIndex => $project)
            @include('bac-secretariat.competitive-bidding.observers.project', ['path' => $path.'.projects.'.$projectIndex])
        @endforeach
    </ol>
    @if ($editable ?? false)
        <button type="button" class="observer-row-add no-print" data-action-icon="false" data-observer-add="project">Add Project</button>
    @endif
</div>
