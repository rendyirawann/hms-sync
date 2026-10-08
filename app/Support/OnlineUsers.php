<?php

namespace App\Support;

use Illuminate\Support\Facades\Redis;
use Throwable;

/** Online users in a Redis sorted set (member = user id, score = last seen). */
class OnlineUsers
{
    private const KEY = 'hms:online-users';

    private const WINDOW_SECONDS = 300;

    public static function tracking(): bool
    {
        return config('session.driver') === 'redis';
    }

    public static function touch(string $userId): void
    {
        try {
            $redis = Redis::connection();
            $redis->zadd(self::KEY, now()->getTimestamp(), $userId);
            // Trim stale members occasionally instead of on every request.
            if (random_int(1, 50) === 1) {
                $redis->zremrangebyscore(self::KEY, '-inf', (string) (now()->getTimestamp() - self::WINDOW_SECONDS));
            }
        } catch (Throwable $e) {
            report($e); // never break a page because of a statistic
        }
    }

    public static function count(): ?int
    {
        try {
            return (int) Redis::connection()->zcount(self::KEY, (string) (now()->getTimestamp() - self::WINDOW_SECONDS), '+inf');
        } catch (Throwable) {
            return null;
        }
    }
}
