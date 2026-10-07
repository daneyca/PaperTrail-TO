<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SystemSettingService
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()->get($key)?->value ?? $default;
    }

    public static function set(string $key, mixed $value, ?int $updatedBy = null): void
    {
        SystemSetting::where('key', $key)->update([
            'value' => is_bool($value) ? ($value ? '1' : '0') : $value,
            'updated_by_user_id' => $updatedBy,
        ]);

        self::clearCache();
    }

    public static function group(string $group)
    {
        return self::all()->where('group', $group)->sortBy('sort_order');
    }

    public static function allPublic()
    {
        return self::all()->where('is_public', true);
    }

    public static function clearCache(): void
    {
        Cache::forget('papertrail.settings');
    }

    private static function all()
    {
        return Cache::rememberForever('papertrail.settings', fn () => SystemSetting::orderBy('sort_order')->get()->keyBy('key'));
    }
}
