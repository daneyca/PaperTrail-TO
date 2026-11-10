<section class="observer-activity" data-observer-section>
    <div class="observer-activity__heading">
        <span aria-hidden="true">-</span>
        @include('bac-secretariat.competitive-bidding.observers.field', ['path' => $path.'.heading', 'value' => $section['heading'] ?? '', 'label' => 'Activity heading', 'placeholder' => 'Enter activity heading'])
        @if ($editable ?? false)
            <button type="button" class="observer-row-remove no-print" data-action-icon="false" data-observer-remove="section">Remove activity</button>
        @endif
    </div>
    <div data-observer-schedules>
        @foreach ($section['schedules'] ?? [] as $scheduleIndex => $schedule)
            @include('bac-secretariat.competitive-bidding.observers.schedule', ['path' => $path.'.schedules.'.$scheduleIndex])
        @endforeach
    </div>
    @if ($editable ?? false)
        <button type="button" class="observer-row-add no-print" data-action-icon="false" data-observer-add="schedule">Add Schedule</button>
    @endif
</section>
