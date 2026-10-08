<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Hotel extends Model
{
    protected $fillable = [
        'name', 'address', 'description', 'phone', 'email', 'rating', 'image', 'status',
        'check_in_time', 'check_out_time',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'status' => 'boolean',
    ];

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
