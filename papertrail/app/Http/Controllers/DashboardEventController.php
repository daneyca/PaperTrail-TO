<?php

namespace App\Http\Controllers;

use App\Models\DashboardEvent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DashboardEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $month = $this->monthFromRequest($request);

        $events = DashboardEvent::query()
            ->visibleTo($user)
            ->whereBetween('event_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->get()
            ->map(fn (DashboardEvent $event): array => $this->eventPayload($event))
            ->values();

        return response()->json(['events' => $events]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request, $user);

        $event = DashboardEvent::create($this->eventAttributes($data, $user));

        AuditLogger::log('Dashboard Calendar', 'Calendar Event Created', 'Dashboard calendar event created.', $event);

        return back()->with('status', 'Calendar event saved.');
    }

    public function update(Request $request, DashboardEvent $event): RedirectResponse
    {
        $user = $request->user();
        abort_unless($event->canBeManagedBy($user), 403);

        $data = $this->validated($request, $user);
        $event->update($this->eventAttributes($data, $user, $event));

        AuditLogger::log('Dashboard Calendar', 'Calendar Event Updated', 'Dashboard calendar event updated.', $event);

        return back()->with('status', 'Calendar event updated.');
    }

    public function destroy(Request $request, DashboardEvent $event): RedirectResponse
    {
        $user = $request->user();
        abort_unless($event->canBeManagedBy($user), 403);

        $event->delete();

        AuditLogger::log('Dashboard Calendar', 'Calendar Event Deleted', 'Dashboard calendar event deleted.');

        return back()->with('status', 'Calendar event deleted.');
    }

    private function validated(Request $request, User $user): array
    {
        $allowedVisibility = $user->isAdmin()
            ? [
                DashboardEvent::VISIBILITY_PRIVATE,
                DashboardEvent::VISIBILITY_OFFICE,
                DashboardEvent::VISIBILITY_ROLE,
                DashboardEvent::VISIBILITY_ALL,
            ]
            : [
                DashboardEvent::VISIBILITY_PRIVATE,
                DashboardEvent::VISIBILITY_OFFICE,
            ];

        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'event_date' => ['required', 'date'],
            'event_time' => ['nullable', 'date_format:H:i'],
            'event_type' => ['nullable', 'string', 'max:40'],
            'visibility' => ['required', Rule::in($allowedVisibility)],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);
    }

    private function eventAttributes(array $data, User $user, ?DashboardEvent $event = null): array
    {
        $visibility = $data['visibility'] ?? DashboardEvent::VISIBILITY_OFFICE;

        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'event_date' => $data['event_date'],
            'event_time' => $data['event_time'] ?? null,
            'event_type' => ($data['event_type'] ?? null) ?: DashboardEvent::TYPE_REMINDER,
            'visibility' => $visibility,
            'office_id' => $visibility === DashboardEvent::VISIBILITY_OFFICE
                ? ($user->isAdmin() ? ($data['office_id'] ?? $user->office_id) : $user->office_id)
                : null,
            'role_id' => $visibility === DashboardEvent::VISIBILITY_ROLE
                ? ($user->isAdmin() ? ($data['role_id'] ?? $user->role_id) : null)
                : null,
            'created_by' => $event?->created_by ?? $user->id,
            'color' => $this->colorFor(($data['event_type'] ?? null) ?: DashboardEvent::TYPE_REMINDER),
        ];
    }

    private function colorFor(?string $type): string
    {
        return match ($type) {
            DashboardEvent::TYPE_DEADLINE => '#f59e0b',
            DashboardEvent::TYPE_MEETING => '#2563eb',
            DashboardEvent::TYPE_DOCUMENT => '#7c3aed',
            DashboardEvent::TYPE_APPROVAL => '#16a34a',
            DashboardEvent::TYPE_SYSTEM => '#0f172a',
            default => '#0ea5e9',
        };
    }

    private function monthFromRequest(Request $request): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', (string) $request->query('calendar_month'))->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    private function eventPayload(DashboardEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'date' => $event->event_date?->toDateString(),
            'time' => $this->formatTime($event->event_time),
            'type' => $event->event_type,
            'visibility' => $event->visibility,
            'color' => $event->color,
        ];
    }

    private function formatTime(mixed $time): ?string
    {
        if (! $time) {
            return null;
        }

        if ($time instanceof Carbon) {
            return $time->format('H:i');
        }

        try {
            return Carbon::parse((string) $time)->format('H:i');
        } catch (\Throwable) {
            return (string) $time;
        }
    }
}
