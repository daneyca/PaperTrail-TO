@props([
    'calendar' => [],
])

@php
    $weekDays = $calendar['weekDays'] ?? [];
    $selectedEvents = $calendar['selectedEvents'] ?? [];
    $upcoming = $calendar['upcoming'] ?? [];
    $visibilityOptions = $calendar['visibilityOptions'] ?? [];
    $eventTypes = $calendar['eventTypes'] ?? [];
    $offices = $calendar['offices'] ?? [];
    $roles = $calendar['roles'] ?? [];
@endphp

<article class="dashboard-support-card compact-calendar-card">
    <div class="compact-calendar-header">
        <div class="compact-calendar-title">
            <span class="support-icon-pill">
                <x-papertrail.icon name="clock" class="h-4 w-4" />
            </span>
            <div>
                <p class="eyebrow">Calendar</p>
                <h2>{{ $calendar['todayLabel'] ?? ('Today, ' . now()->format('M d')) }}</h2>
                <small>{{ $calendar['weekLabel'] ?? now()->startOfWeek()->format('M d') . ' - ' . now()->endOfWeek()->format('M d') }}</small>
            </div>
        </div>

        @if ($calendar['canCreate'] ?? false)
            <details class="compact-event-menu">
                <summary>Add Event</summary>
                <form method="POST" action="{{ route('dashboard-events.store') }}" class="dashboard-event-form compact-dashboard-event-form">
                    @csrf
                    <label>
                        <span>Title</span>
                        <input type="text" name="title" maxlength="160" required placeholder="Deadline, meeting, or reminder">
                    </label>

                    <div class="event-form-split">
                        <label>
                            <span>Date</span>
                            <input type="date" name="event_date" value="{{ $calendar['selectedDate'] ?? now()->toDateString() }}" required>
                        </label>
                        <label>
                            <span>Time</span>
                            <input type="time" name="event_time">
                        </label>
                    </div>

                    <div class="event-form-split">
                        <label>
                            <span>Type</span>
                            <select name="event_type">
                                @foreach ($eventTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Visibility</span>
                            <select name="visibility">
                                @foreach ($visibilityOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($value === 'office')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    @if ($calendar['isAdmin'] ?? false)
                        <div class="event-form-split">
                            <label>
                                <span>Office</span>
                                <select name="office_id">
                                    <option value="">Use my office</option>
                                    @foreach ($offices as $office)
                                        <option value="{{ $office['id'] }}">{{ $office['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Role</span>
                                <select name="role_id">
                                    <option value="">Select role</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role['id'] }}">{{ $role['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    @endif

                    <label>
                        <span>Description</span>
                        <textarea name="description" rows="2" placeholder="Optional context"></textarea>
                    </label>

                    <div class="dashboard-event-form-actions">
                        <button type="reset" class="event-cancel-button">Cancel</button>
                        <button type="submit">Save Event</button>
                    </div>
                </form>
            </details>
        @endif
    </div>

    <div class="compact-week-nav" aria-label="Calendar week controls">
        <a href="{{ request()->fullUrlWithQuery(['calendar_date' => $calendar['previousWeek'] ?? now()->subWeek()->toDateString()]) }}" aria-label="Previous week">&lsaquo;</a>
        <span>{{ $calendar['weekLabel'] ?? now()->format('F Y') }}</span>
        <a href="{{ request()->fullUrlWithQuery(['calendar_date' => $calendar['nextWeek'] ?? now()->addWeek()->toDateString()]) }}" aria-label="Next week">&rsaquo;</a>
    </div>

    <div class="compact-week-strip" aria-label="Week dates">
        @foreach ($weekDays as $day)
            <a href="{{ request()->fullUrlWithQuery(['calendar_date' => $day['date']]) }}"
                class="compact-day-pill {{ ! empty($day['isSelected']) ? 'is-selected' : '' }} {{ ! empty($day['isToday']) ? 'is-today' : '' }}">
                <span>{{ $day['weekday'] }}</span>
                <strong>{{ str_pad((string) $day['day'], 2, '0', STR_PAD_LEFT) }}</strong>
                @if (($day['eventsCount'] ?? 0) > 0)
                    <small>{{ $day['eventsCount'] }}</small>
                @else
                    <i aria-hidden="true"></i>
                @endif
            </a>
        @endforeach
    </div>

    <div class="compact-calendar-events">
        <div class="support-subheading">
            <span>{{ $calendar['selectedDateLabel'] ?? 'Today' }} Events</span>
        </div>

        @forelse ($selectedEvents as $event)
            <div class="compact-calendar-event">
                <span class="compact-event-accent" style="--event-color: {{ $event['color'] ?? '#2563eb' }}"></span>
                <div>
                    <strong>{{ $event['title'] }}</strong>
                    <p>{{ $event['description'] ?: $event['typeLabel'] }}</p>
                </div>
                <time>{{ $event['timeLabel'] ?? 'All day' }}</time>
            </div>
        @empty
            <div class="support-empty-state compact-calendar-empty">
                <strong>No calendar events scheduled.</strong>
            </div>
        @endforelse
    </div>

    @if (empty($selectedEvents) && ! empty($upcoming))
        <div class="compact-upcoming-list">
            <div class="support-subheading">
                <span>Next Up</span>
            </div>
            @foreach (array_slice($upcoming, 0, 2) as $event)
                <div class="compact-upcoming-item">
                    <span class="calendar-type-dot" style="--event-color: {{ $event['color'] ?? '#2563eb' }}"></span>
                    <div>
                        <strong>{{ $event['title'] }}</strong>
                        <small>{{ $event['dateLabel'] }}{{ ! empty($event['timeLabel']) ? ' - ' . $event['timeLabel'] : '' }}</small>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</article>
