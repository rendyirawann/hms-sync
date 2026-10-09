<?php

namespace App\Support;

use App\Models\Hotel;

/**
 * The hotel the current request works on.
 *
 * - Hotel staff (admin / Pegawai): always their own hotel, enforced.
 * - Superadmin: the hotel picked with "Kelola hotel" (session), enforced
 *   while picked; none picked means platform-wide views.
 * - Guests and the public site: not enforced; queries filter explicitly.
 *
 * Registered as a scoped binding, so it resets on every Octane request.
 */
class HotelContext
{
    private ?Hotel $hotel = null;

    private bool $enforced = false;

    public function set(?Hotel $hotel, bool $enforce = true): void
    {
        $this->hotel = $hotel;
        $this->enforced = $hotel !== null && $enforce;
    }

    public function hotel(): ?Hotel
    {
        return $this->hotel;
    }

    public function id(): ?int
    {
        return $this->hotel?->id;
    }

    /** Hotel id the global scope filters on, or null when not enforced. */
    public function scopeId(): ?int
    {
        return $this->enforced ? $this->hotel?->id : null;
    }

    /** Run a callback with the scope switched off (platform jobs, schedulers). */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->enforced;
        $this->enforced = false;

        try {
            return $callback();
        } finally {
            $this->enforced = $previous;
        }
    }
}
