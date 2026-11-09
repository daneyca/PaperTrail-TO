<?php

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SystemNotificationService
{
    public static function queryForUser(User $user): \Illuminate\Database\Eloquent\Builder
    {
        return SystemNotification::query()->whereIn('user_id', app(BacsecMpdoWorkspaceService::class)->notificationOwnerIds($user));
    }

    public static function sendToUser(
        int|User|null $user,
        string $title,
        string $message,
        string $type = SystemNotification::TYPE_INFO,
        string $module = 'System',
        ?string $actionUrl = null,
        ?Model $related = null,
    ): ?SystemNotification {
        $target = $user instanceof User ? $user : User::find($user);

        return self::notify($target, $title, $message, $type, $module, $related, $actionUrl);
    }

    public static function sendToRole(
        string $role,
        string $title,
        string $message,
        string $type = SystemNotification::TYPE_INFO,
        string $module = 'System',
        ?string $actionUrl = null,
        ?Model $related = null,
    ): int {
        return User::where('role', $role)
            ->where('status', User::STATUS_ACTIVE)
            ->get()
            ->map(fn (User $user) => self::notify($user, $title, $message, $type, $module, $related, $actionUrl))
            ->filter()
            ->count();
    }

    public static function unreadCount(User $user): int
    {
        return self::queryForUser($user)->whereNull('read_at')->count();
    }

    public static function latestForUser(User $user, int $limit = 5)
    {
        return self::queryForUser($user)->latest()->limit($limit)->get();
    }

    public static function notify(
        ?User $user,
        string $title,
        string $message,
        string $type = SystemNotification::TYPE_INFO,
        ?string $module = null,
        ?Model $related = null,
        ?string $actionUrl = null,
    ): ?SystemNotification {
        if (! $user) {
            return null;
        }

        return app(NotificationDispatchService::class)->notifyUser(
            user: $user,
            subject: $title,
            message: $message,
            url: $actionUrl,
            metadata: [],
            type: $type,
            module: $module,
            related: $related,
        );
    }
}
