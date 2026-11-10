<?php

namespace App\Http\Controllers;

use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        AuditLogger::log('Notifications', $this->auditAction($request, 'Notifications Page Viewed'), 'User viewed notifications.');

        $base = SystemNotificationService::queryForUser($request->user());
        $query = (clone $base)->latest();

        $this->applyFilters($query, $request);

        return view('notifications.index', [
            'notifications' => $query->paginate(10)->withQueryString(),
            'subtitle' => $this->subtitle($request),
            'filters' => $request->only(['search', 'type', 'module', 'status', 'date_from', 'date_to']),
            'counts' => [
                'all' => (clone $base)->count(),
                'unread' => (clone $base)->whereNull('read_at')->count(),
                'read' => (clone $base)->whereNotNull('read_at')->count(),
                'today' => (clone $base)->whereDate('created_at', today())->count(),
            ],
            'types' => [SystemNotification::TYPE_INFO, SystemNotification::TYPE_SUCCESS, SystemNotification::TYPE_WARNING, SystemNotification::TYPE_ERROR],
            'modules' => (clone $base)->whereNotNull('module')->select('module')->distinct()->orderBy('module')->pluck('module'),
        ]);
    }

    public function show(Request $request, SystemNotification $notification): View|RedirectResponse
    {
        $this->authorizeOwner($request, $notification);
        $destination = $this->relatedPageUrl($notification);

        if (!$destination) {
            return $this->details($request, $notification);
        }

        if (!$notification->isRead()) {
            $notification->update(['read_at' => now()]);
        }

        AuditLogger::log('Notifications', $this->auditAction($request, 'Notification Opened'), 'User opened the page related to a notification.', $notification);

        return redirect($destination);
    }

    public function details(Request $request, SystemNotification $notification): View
    {
        $this->authorizeOwner($request, $notification);

        if (! $notification->isRead()) {
            $notification->update(['read_at' => now()]);
        }

        AuditLogger::log('Notifications', $this->auditAction($request, 'Notification Detail Viewed'), 'User opened notification detail.', $notification);

        $relatedPageUrl = $this->relatedPageUrl($notification);

        return view('notifications.show', [
            'notification' => $notification,
            'relatedPageUrl' => $relatedPageUrl,
            'destinationUnavailable' => filled($notification->action_url) && !$relatedPageUrl,
        ]);
    }

    public function markRead(Request $request, SystemNotification $notification): RedirectResponse
    {
        $this->authorizeOwner($request, $notification);

        if (! $notification->isRead()) {
            $notification->update(['read_at' => now()]);
        }

        AuditLogger::log('Notifications', $this->auditAction($request, 'Notification Marked as Read'), 'User marked notification as read.', $notification);

        if ($destination = $this->relatedPageUrl($notification)) {
            return redirect($destination);
        }

        return back()->with('status', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        SystemNotificationService::queryForUser($request->user())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        AuditLogger::log('Notifications', $this->auditAction($request, 'All Notifications Marked as Read'), 'User marked all notifications as read.');

        return back()->with('status', 'All notifications marked as read.');
    }

    public function destroy(Request $request, SystemNotification $notification): RedirectResponse
    {
        $this->authorizeOwner($request, $notification);

        $notification->delete();

        AuditLogger::log('Notifications', $this->auditAction($request, 'Notification Deleted'), 'User deleted notification.');

        return redirect()
            ->route('notifications.index')
            ->with('status', 'Notification deleted.');
    }

    public function clearRead(Request $request): RedirectResponse
    {
        $deleted = SystemNotificationService::queryForUser($request->user())
            ->whereNotNull('read_at')
            ->delete();

        AuditLogger::log('Notifications', $this->auditAction($request, 'Read Notifications Cleared'), "User cleared {$deleted} read notifications.");

        return back()->with('status', 'Read notifications cleared.');
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $status = $request->query('status', $request->query('filter', 'all'));

        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = $request->string('search')->toString();
            $builder->where(function (Builder $nested) use ($search) {
                $nested->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        });

        $query->when($request->filled('type'), fn (Builder $builder) => $builder->where('type', $request->input('type')));
        $query->when($request->filled('module'), fn (Builder $builder) => $builder->where('module', $request->input('module')));

        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        }

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('created_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('created_at', '<=', $request->date('date_to')));
    }

    private function relatedPageUrl(SystemNotification $notification): ?string
    {
        $url = trim((string) $notification->action_url);
        if ($url === '' || preg_match('/[\x00-\x1f\x7f]/', $url)) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https'], true))) {
            return null;
        }
        $path = $parts['path'] ?? '';
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return null;
        }

        if ($notification->related_type && $notification->related_id) {
            $model = Relation::getMorphedModel($notification->related_type) ?: $notification->related_type;
            if (is_subclass_of($model, Model::class) && !$model::query()->whereKey($notification->related_id)->exists()) {
                return null;
            }
        }

        // Use the current application origin, including for links saved before a domain change.
        $destination = $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
        try {
            $router = app('router');
            $route = $router->getRoutes()->match(Request::create($destination, 'GET'));
            if (in_array($route->getName(), ['notifications.show', 'notifications.details'], true)) {
                return null;
            }
            $router->substituteBindings($route);
            $router->substituteImplicitBindings($route);
        } catch (ModelNotFoundException|NotFoundHttpException|MethodNotAllowedHttpException|\InvalidArgumentException $exception) {
            return null;
        }

        return $destination . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    private function authorizeOwner(Request $request, SystemNotification $notification): void
    {
        if (SystemNotificationService::queryForUser($request->user())->whereKey($notification->id)->exists()) {
            return;
        }

        AuditLogger::log('Notifications', $this->auditAction($request, 'Unauthorized Notification Access Attempt'), 'User attempted to access another user notification.', $notification, null, null, 'warning');

        abort(403);
    }

    private function auditAction(Request $request, string $default): string
    {
        if ($request->user()?->role === User::ROLE_BAC_MEMBER) {
            return match ($default) {
                'Notifications Page Viewed' => 'BAC Member Notifications Page Viewed',
                'Notification Detail Viewed' => 'BAC Member Notification Detail Viewed',
                'Notification Marked as Read' => 'BAC Member Notification Marked as Read',
                'All Notifications Marked as Read' => 'BAC Member All Notifications Marked as Read',
                'Unauthorized Notification Access Attempt' => 'BAC Member Unauthorized Notification Access Attempt',
                default => $default,
            };
        }

        if ($request->user()?->role === User::ROLE_BAC_CHAIR) {
            return match ($default) {
                'Notifications Page Viewed' => 'BAC Chair Notifications Page Viewed',
                'Notification Detail Viewed' => 'BAC Chair Notification Detail Viewed',
                'Notification Marked as Read' => 'BAC Chair Notification Marked as Read',
                'All Notifications Marked as Read' => 'BAC Chair All Notifications Marked as Read',
                'Unauthorized Notification Access Attempt' => 'BAC Chair Unauthorized Notification Access Attempt',
                default => $default,
            };
        }

        return $default;
    }

    private function subtitle(Request $request): string
    {
        if ($request->user()?->role === User::ROLE_BAC_CHAIR) {
            return 'View BAC approval alerts, confirmation updates, routing notices, and system messages.';
        }

        return 'View system alerts, routing updates, and pending actions.';
    }
}
