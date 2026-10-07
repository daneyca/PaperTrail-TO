<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DashboardEvent;
use App\Models\DocumentAttachment;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class DashboardWidgetService
{
    public function calendarData(User $user, ?string $monthParam = null, ?string $dateParam = null): array
    {
        $selectedDate = $this->dateFrom($dateParam);
        $weekStart = $selectedDate->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $selectedDate->copy()->endOfWeek(Carbon::SUNDAY);

        $events = DashboardEvent::query()
            ->visibleTo($user)
            ->with(['creator', 'office', 'role'])
            ->whereBetween('event_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->get();

        $eventsByDate = $events->groupBy(fn (DashboardEvent $event): string => $event->event_date->toDateString());
        $weekDays = [];

        for ($date = $weekStart->copy(); $date->lte($weekEnd); $date->addDay()) {
            $dateKey = $date->toDateString();
            $dayEvents = $eventsByDate->get($dateKey, collect());

            $weekDays[] = [
                'date' => $dateKey,
                'weekday' => $date->format('D'),
                'day' => $date->day,
                'isToday' => $date->isToday(),
                'isSelected' => $date->isSameDay($selectedDate),
                'eventsCount' => $dayEvents->count(),
                'events' => $dayEvents->take(3)->map(fn (DashboardEvent $event): array => $this->eventSummary($event))->values()->all(),
            ];
        }

        $selectedEvents = $eventsByDate
            ->get($selectedDate->toDateString(), collect())
            ->map(fn (DashboardEvent $event): array => $this->eventSummary($event))
            ->values()
            ->all();

        $upcoming = DashboardEvent::query()
            ->visibleTo($user)
            ->with(['creator', 'office', 'role'])
            ->whereDate('event_date', '>=', today()->toDateString())
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->limit(5)
            ->get()
            ->map(fn (DashboardEvent $event): array => $this->eventSummary($event))
            ->values()
            ->all();

        return [
            'todayLabel' => 'Today, ' . now()->format('M d'),
            'selectedDate' => $selectedDate->toDateString(),
            'selectedDateLabel' => $selectedDate->isToday() ? 'Today' : $selectedDate->format('M d, Y'),
            'weekLabel' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
            'previousWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'weekDays' => $weekDays,
            'selectedEvents' => $selectedEvents,
            'upcoming' => $upcoming,
            'canCreate' => true,
            'isAdmin' => $user->isAdmin(),
            'offices' => $this->optionList(Office::query()->where('status', Office::STATUS_ACTIVE)->orderBy('name')->get(), 'name'),
            'roles' => $this->optionList(Role::query()->where('status', Role::STATUS_ACTIVE)->orderBy('name')->get(), 'name'),
            'visibilityOptions' => $user->isAdmin()
                ? [
                    DashboardEvent::VISIBILITY_PRIVATE => 'Only me',
                    DashboardEvent::VISIBILITY_OFFICE => 'My office',
                    DashboardEvent::VISIBILITY_ROLE => 'Role group',
                    DashboardEvent::VISIBILITY_ALL => 'All users',
                ]
                : [
                    DashboardEvent::VISIBILITY_PRIVATE => 'Only me',
                    DashboardEvent::VISIBILITY_OFFICE => 'My office',
                ],
            'eventTypes' => [
                DashboardEvent::TYPE_REMINDER => 'Reminder',
                DashboardEvent::TYPE_DEADLINE => 'Deadline',
                DashboardEvent::TYPE_MEETING => 'Meeting',
                DashboardEvent::TYPE_DOCUMENT => 'Document',
                DashboardEvent::TYPE_APPROVAL => 'Approval',
                DashboardEvent::TYPE_SYSTEM => 'System',
            ],
        ];
    }

    public function recentEmails(User $user): array
    {
        $logs = AuditLog::query()
            ->where('module', 'Email Notifications')
            ->whereIn('action', [
                'Email Notification Sent',
                'Email Notification Failed',
                'Email Notification Skipped',
            ])
            ->when(! $user->isAdmin(), function (Builder $query) use ($user): void {
                $query->where(function (Builder $scope) use ($user): void {
                    $scope->where('user_id', $user->id);

                    if ($user->office_id) {
                        $scope->orWhere('office_id', $user->office_id);
                    }

                    if ($user->user_id) {
                        $scope->orWhere('metadata->recipient_user_id', $user->user_id);
                    }
                });
            })
            ->latest()
            ->limit(6)
            ->get();

        return [
            'items' => $logs->map(fn (AuditLog $log): array => $this->emailSummary($log))->values()->all(),
        ];
    }

    public function adminStatistics(): array
    {
        $totalUsers = User::count();
        $activeUsers = User::where('status', User::STATUS_ACTIVE)->count();
        $roles = Role::count();
        $offices = Office::count();
        $permissions = Permission::count();
        $auditLogsToday = AuditLog::whereDate('created_at', today())->count();
        $userActivities = AuditLog::query()
            ->where(function (Builder $query): void {
                $query->where('module', 'like', '%User%')
                    ->orWhere('module', 'like', '%Profile%')
                    ->orWhere('module', 'like', '%Authentication%')
                    ->orWhere('module', 'like', '%Role%')
                    ->orWhere('module', 'like', '%Permission%')
                    ->orWhere('action', 'like', '%User%');
            })
            ->count();
        $systemLogs = AuditLog::query()
            ->whereIn('module', ['Rules Configuration', 'Settings', 'Workflow', 'Permissions', 'Roles', 'Offices', 'System'])
            ->count();
        $aiEvents = AuditLog::where('module', 'like', 'AI%')->count();
        $aiPending = DocumentAttachment::query()
            ->where(function (Builder $query): void {
                $query->whereIn('ai_analysis_status', [DocumentAttachment::AI_PENDING, DocumentAttachment::AI_QUEUED])
                    ->orWhereIn('ocr_status', [DocumentAttachment::OCR_PENDING, DocumentAttachment::OCR_QUEUED]);
            })
            ->count();

        return [
            'kpis' => [
                ['label' => 'Total Users', 'value' => $totalUsers, 'tone' => 'blue', 'icon' => 'users', 'href' => $this->routeUrl('admin.users.index'), 'description' => 'Registered accounts'],
                ['label' => 'Active Users', 'value' => $activeUsers, 'tone' => 'green', 'icon' => 'check', 'href' => $this->routeUrl('admin.users.index'), 'description' => 'Accounts currently enabled'],
                ['label' => 'Roles', 'value' => $roles, 'tone' => 'violet', 'icon' => 'shield', 'href' => $this->routeUrl('admin.roles.index'), 'description' => 'Configured access roles'],
                ['label' => 'Offices', 'value' => $offices, 'tone' => 'orange', 'icon' => 'document', 'href' => $this->routeUrl('admin.offices.index'), 'description' => 'Registered offices'],
                ['label' => 'Permissions', 'value' => $permissions, 'tone' => 'blue', 'icon' => 'key', 'href' => $this->routeUrl('admin.roles.index'), 'description' => 'Permission keys available'],
            ],
            'monitoring' => [
                ['label' => 'Audit Trail Summary', 'value' => $auditLogsToday, 'description' => 'logs recorded today', 'tone' => 'blue', 'href' => $this->routeUrl('admin.audit.index')],
                ['label' => 'User Activities', 'value' => $userActivities, 'description' => 'account, profile, role, and permission events', 'tone' => 'green', 'href' => $this->routeUrl('admin.audit.index')],
                ['label' => 'System Logs', 'value' => $systemLogs, 'description' => 'settings, workflow rule, office, and role changes', 'tone' => 'orange', 'href' => $this->routeUrl('admin.audit.index')],
            ],
            'aiCenter' => [
                ['label' => 'AI Processing Status', 'value' => $aiPending, 'description' => 'pending OCR or AI processing jobs', 'tone' => 'violet', 'href' => $this->routeUrl('ai-document-verification.index')],
                ['label' => 'AI Usage Summary', 'value' => $aiEvents, 'description' => 'AI audit events recorded', 'tone' => 'blue', 'href' => $this->routeUrl('admin.ai-summary.index')],
                ['label' => 'AI Configuration Status', 'value' => Route::has('admin.ai-summary.index') ? 'Ready' : 'Setup', 'description' => 'AI summary module availability', 'tone' => 'green', 'href' => $this->routeUrl('admin.settings.index')],
            ],
            'documentFlow' => [],
            'recentActivity' => AuditLog::query()
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (AuditLog $log): array => [
                    'action' => $log->action ?: 'System activity',
                    'module' => $log->module ?: 'System',
                    'time' => $log->created_at?->diffForHumans() ?? 'Recently',
                    'status' => $log->status ?: 'recorded',
                ])
                ->values()
                ->all(),
        ];
    }

    private function monthFrom(?string $monthParam): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', (string) $monthParam)->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    private function dateFrom(?string $dateParam): Carbon
    {
        try {
            return Carbon::parse((string) $dateParam)->startOfDay();
        } catch (\Throwable) {
            return today();
        }
    }

    private function eventSummary(DashboardEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'date' => $event->event_date?->toDateString(),
            'dateLabel' => $event->event_date?->format('M d, Y') ?? 'No date',
            'timeLabel' => $this->timeLabel($event->event_time),
            'type' => $event->event_type ?: DashboardEvent::TYPE_REMINDER,
            'typeLabel' => ucfirst(str_replace('_', ' ', (string) ($event->event_type ?: DashboardEvent::TYPE_REMINDER))),
            'visibility' => $event->visibility,
            'color' => $event->color ?: '#2563eb',
        ];
    }

    private function emailSummary(AuditLog $log): array
    {
        $metadata = $log->metadata ?? [];

        return [
            'subject' => $metadata['subject'] ?? $log->target_label ?? $log->action,
            'description' => $log->description ?: 'Email notification recorded.',
            'recipient' => $metadata['recipient_user_id'] ?? $log->user_identifier ?? null,
            'status' => match ($log->action) {
                'Email Notification Sent' => 'Sent',
                'Email Notification Failed' => 'Failed',
                'Email Notification Skipped' => 'Skipped',
                default => $log->status ?: 'Recorded',
            },
            'tone' => match ($log->action) {
                'Email Notification Sent' => 'success',
                'Email Notification Failed' => 'danger',
                'Email Notification Skipped' => 'warning',
                default => 'muted',
            },
            'time' => $log->created_at?->format('M d, Y h:i A') ?? 'Recently',
        ];
    }

    private function optionList(Collection $items, string $labelField): array
    {
        return $items
            ->map(fn ($item): array => [
                'id' => $item->getKey(),
                'label' => $item->{$labelField},
            ])
            ->values()
            ->all();
    }

    private function timeLabel(mixed $time): ?string
    {
        if (! $time) {
            return null;
        }

        if ($time instanceof Carbon) {
            return $time->format('h:i A');
        }

        try {
            return Carbon::parse((string) $time)->format('h:i A');
        } catch (\Throwable) {
            return (string) $time;
        }
    }

    private function routeUrl(string $name, array $parameters = []): ?string
    {
        return Route::has($name) ? route($name, $parameters) : null;
    }
}
