<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Once;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key.
     *
     * Reads from the single "settings.all" cache entry instead of one cache
     * entry per key (each of those was a round trip to the cache store), and
     * no longer caches $default for a missing key.
     */
    public static function get(string $key, $default = null)
    {
        return static::allCached()[$key] ?? $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting.{$key}");
        Cache::forget('settings.all');
        Once::flush();
    }

    /**
     * Get all settings as key-value array (cached).
     */
    public static function allCached(): array
    {
        // once(): one cache read per request. Octane resets it between
        // requests, so a long-lived worker never serves stale settings.
        return once(fn () => Cache::remember('settings.all', 3600, function () {
            return static::pluck('value', 'key')->toArray();
        }));
    }

    /**
     * Clear all settings cache.
     */
    public static function clearCache(): void
    {
        $settings = static::pluck('key');
        foreach ($settings as $key) {
            Cache::forget("setting.{$key}");
        }
        Cache::forget('settings.all');
        Once::flush();
    }
}
