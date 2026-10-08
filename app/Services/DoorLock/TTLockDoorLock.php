<?php

namespace App\Services\DoorLock;

use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TTLock Open Platform (v3) driver.
 * Docs: https://euopen.ttlock.com/document
 *
 * Codes are sent through the lock's gateway (addType/deleteType = 2), so the
 * lock must be paired with a G2/G3 gateway. Rooms without a lock_id are
 * skipped, which lets a hotel connect its doors gradually.
 */
class TTLockDoorLock implements DoorLock
{
    public function grant(Room $room, string $code, CarbonInterface $validFrom, CarbonInterface $validUntil): void
    {
        if (blank($room->lock_id)) {
            return;
        }

        // A previous guest's code must never keep working.
        $this->revoke($room);

        $data = $this->call('/v3/keyboardPwd/add', [
            'lockId' => $room->lock_id,
            'keyboardPwd' => $code,
            'keyboardPwdName' => 'HMS ' . $room->room_number,
            'startDate' => $validFrom->getTimestampMs(),
            'endDate' => $validUntil->getTimestampMs(),
            'addType' => 2,
        ]);

        $room->forceFill(['lock_code_ref' => (string) ($data['keyboardPwdId'] ?? '')])->saveQuietly();
    }

    public function revoke(Room $room): void
    {
        if (blank($room->lock_id) || blank($room->lock_code_ref)) {
            return;
        }

        try {
            $this->call('/v3/keyboardPwd/delete', [
                'lockId' => $room->lock_id,
                'keyboardPwdId' => $room->lock_code_ref,
                'deleteType' => 2,
            ]);
        } catch (DoorLockException $e) {
            // -3008 / not found: already gone on the lock, nothing left to revoke.
            if (! str_contains($e->getMessage(), '-3008')) {
                throw $e;
            }
        }

        $room->forceFill(['lock_code_ref' => null])->saveQuietly();
    }

    private function call(string $path, array $params, bool $retried = false): array
    {
        $response = Http::asForm()->timeout(15)->post($this->base() . $path, $params + [
            'clientId' => config('hms.ttlock.client_id'),
            'accessToken' => $this->token(),
            'date' => now()->getTimestampMs(),
        ]);

        $data = $response->json() ?? [];

        // 10003/10004/10011: token expired or revoked -> log in again once.
        if (! $retried && in_array((int) ($data['errcode'] ?? 0), [10003, 10004, 10011], true)) {
            Cache::forget('ttlock.token');

            return $this->call($path, $params, true);
        }

        if (! $response->successful() || (isset($data['errcode']) && (int) $data['errcode'] !== 0)) {
            $message = sprintf('TTLock %s gagal (%s): %s', $path, $data['errcode'] ?? $response->status(), $data['errmsg'] ?? $data['description'] ?? 'tidak ada respons');
            Log::warning('door-lock.ttlock', ['path' => $path, 'lockId' => $params['lockId'] ?? null, 'response' => $data]);

            throw new DoorLockException($message);
        }

        return $data;
    }

    /** OAuth token, cached until shortly before it expires. */
    private function token(): string
    {
        return Cache::remember('ttlock.token', now()->addDays(80), function () {
            $response = Http::asForm()->timeout(15)->post($this->base() . '/oauth2/token', [
                'clientId' => config('hms.ttlock.client_id'),
                'clientSecret' => config('hms.ttlock.client_secret'),
                'username' => config('hms.ttlock.username'),
                // TTLock expects the account password as lowercase MD5.
                'password' => md5((string) config('hms.ttlock.password')),
            ]);

            $token = $response->json('access_token');

            if (! $token) {
                throw new DoorLockException('Login TTLock gagal: ' . ($response->json('errmsg') ?? $response->json('description') ?? 'HTTP ' . $response->status()));
            }

            return $token;
        });
    }

    private function base(): string
    {
        return rtrim((string) config('hms.ttlock.base_url', 'https://euapi.ttlock.com'), '/');
    }
}
