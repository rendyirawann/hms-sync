<?php

namespace App\Services\DoorLock;

use App\Models\Room;
use Carbon\CarbonInterface;

/**
 * Bridge to a smart-lock vendor (TTLock, Nuki, ...). Implementations must be
 * idempotent: grant() replaces any previous code on that room's lock.
 */
interface DoorLock
{
    public function grant(Room $room, string $code, CarbonInterface $validFrom, CarbonInterface $validUntil): void;

    public function revoke(Room $room): void;
}
