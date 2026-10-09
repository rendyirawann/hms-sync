<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RoomType extends Model
{
    use BelongsToHotel;

    protected $fillable = [
        'hotel_id', 'name', 'description', 'capacity', 'base_price', 'amenities', 'image', 'is_active',
    ];

    protected $casts = [
        'amenities' => 'array',
        'base_price' => 'decimal:2',
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];


    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function images()
    {
        return $this->hasMany(RoomTypeImage::class)->orderBy('sort');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function rateRules()
    {
        return $this->hasMany(RateRule::class);
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
