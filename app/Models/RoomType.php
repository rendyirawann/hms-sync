<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RoomType extends Model
{
    protected $fillable = [
        'hotel_id', 'name', 'description', 'capacity', 'base_price', 'amenities', 'image', 'is_active',
    ];

    protected $casts = [
        'amenities' => 'array',
        'base_price' => 'decimal:2',
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image
            ? Storage::disk('public')->url($this->image)
            : asset('assets/media/stock/600x400/img-1.jpg');
    }
}
