<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Hotel extends Model
{
    protected $fillable = [
        'name', 'address', 'description', 'phone', 'email', 'rating', 'image', 'status',
        'check_in_time', 'check_out_time', 'tax_percent', 'service_percent', 'payment_deadline_hours',
        'bank_accounts', 'latitude', 'longitude', 'clock_in_radius_m', 'clock_in_ips', 'late_grace_minutes',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'status' => 'boolean',
        'tax_percent' => 'decimal:2',
        'service_percent' => 'decimal:2',
        'payment_deadline_hours' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'clock_in_radius_m' => 'integer',
        'late_grace_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        // current() is memoised with once(); a saved change must not be served stale.
        static::saved(fn () => \Illuminate\Support\Once::flush());
    }

    /** The system runs a single property; this returns (or bootstraps) it. */
    public static function current(): self
    {
        // Memoised for the request: header, footer and controllers all ask.
        return once(fn () => static::query()->oldest('id')->first()
            ?? static::create(['name' => config('app.name', 'HMS Hotel'), 'address' => '-']));
    }

    public function roomTypes()
    {
        return $this->hasMany(RoomType::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
