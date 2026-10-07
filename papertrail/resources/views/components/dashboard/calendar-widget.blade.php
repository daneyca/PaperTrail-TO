@props([
    'calendar' => [],
])

@php
    $days = $calendar['days'] ?? [];
    $weekdays = $calendar['weekdays'] ?? ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $upcoming = $calendar['upcoming'] ?? [];
    $visibilityOptions = $calendar['visibilityOptions'] ?? [];
    $eventTypes = $calendar['eventTypes'] ?? [];
    $offices = $calendar['offices'] ?? [];
    $roles = $calendar['roles'] ?? [];
@endphp

<article class="dashboard-support-card dashboard-calendar-card">
    <div class="support-widget-heading">
        <div>
            <p class="eyebrow">Calendar</p>
            <h2>Procurement Schedule</h2>
        </div>
        <div class="calendar-month-controls">
            <a href="{{ request()->fullUrlWithQuery(['calendar_month' => $calendar['previousMonth'] ?? now()->subMonth()->format('Y-m')]) }}" aria-label="Previous month">‹</a>
            <strong>{{ $calendar['monthLabel'] ?? now()->format('F Y') }}</strong>
            <a href="{{ request()->fullUrlWithQuery(['calendar_month' => $calendar['nextMonth'] ?? now()->addMonth()->format('Y-m')]) }}" aria-label="Next month">›</a>
        </div>
    </div>

    <div class="dashboard-calendar-grid" aria-label="{{ $calendar['monthLabel'] ?? 'Dashboard calendar' }}">
        @foreach ($weekdays as $weekday)
            <span class="calendar-weekday">{{ $weekday }}</span>
        @endforeach

        @foreach ($days as $day)
            <div class="calendar-day {{ empty($day['isCurrentMonth']) ? 'is-muted' : '' }} {{ ! empty($day['isToday']) ? 'is-today' : '' }}">
                <span class="calendar-day-number">{{ $day['day'] }}</span>
                @if (! empty($day['events']))
                    <span class="calendar-event-dots">
                        @foreach ($day['events'] as $event)
                            <span class="calendar-event-dot" title="{{ $event['title'] }}" style="--event-color: {{ $event['color'] ?? '#2563eb' }}"></span>
                        @endforeach
                        @if (($day['extraCount'] ?? 0) > 0)
                            <small>+{{ $day['extraCount'] }}</small>
                        @endif
                    </span>
                @endif
            </div>
        @endforeach
    </div>

    <div class="calendar-lower-grid">
        <div>
            <div class="support-subheading">
                <span>Upcoming</span>
            </div>

            <div class="calendar-upcoming-list">
                @forelse ($upcoming as $event)
                    <div class="calendar-upcoming-item">
                        <span class="calendar-type-dot" style="--event-color: {{ $event['color'] ?? '#2563eb' }}"></span>
                        <div>
                            <strong>{{ $event['title'] }}</strong>
                            <small>{{ $event['dateLabel'] }}{{ ! empty($event['timeLabel']) ? ' · ' . $event['timeLabel'] : '' }} · {{ $event['typeLabel'] }}</small>
                        </div>
                    </div>
                @empty
                    <p class="support-empty-text">No calendar events scheduled.</p>
                @endforelse
            </div>
        </div>

        @if ($calendar['canCreate'] ?? false)
            <details class="dashboard-event-drawer">
                <summary>
                    <span>Add Event</span>
                    <strong>+</strong>
                </summary>

                <form method="POST" action="{{ route('dashboard-events.store') }}" class="dashboard-event-form">
                    @csrf
                    <label>
                        <span>Title</span>
                        <input type="text" name="title" maxlength="160" required placeholder="Deadline, meeting, or reminder">
                    </label>

                    <div class="event-form-split">
                        <label>
                            <span>Date</span>
                            <input type="date" name="event_date" value="{{ now()->toDateString() }}" required>
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
                        <textarea name="description" rows="3" placeholder="Optional context"></textarea>
                    </label>

                    <div class="dashboard-event-form-actions">
                        <button type="reset" class="event-cancel-button">Cancel</button>
                        <button type="submit">Save Event</button>
                    </div>
                </form>
            </details>
        @endif
    </div>
</article>
