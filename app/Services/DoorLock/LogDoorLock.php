<?php

namespace App\Services\DoorLock;

use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/** Default driver when no lock hardware is connected: records the action only. */
class LogDoorLock implements DoorLock
{
    public function grant(Room $room, string $code, CarbonInterface $validFrom, CarbonInterface $validUntil): void
    {
        // The code itself is deliberately not logged.
        Log::info('door-lock.grant', [
            'room' => $room->room_number,
            'valid_from' => $validFrom->toIso8601String(),
            'valid_until' => $validUntil->toIso8601String(),
        ]);
    }

    public function revoke(Room $room): void
    {
        Log::info('door-lock.revoke', ['room' => $room->room_number]);
    }
}
