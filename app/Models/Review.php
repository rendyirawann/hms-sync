<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id', 'booking_id', 'user_id', 'room_type_id', 'rating', 'comment', 'is_published'];

    protected $casts = [
        'rating' => 'integer',
        'is_published' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    /** hotel_id follows the parent booking. */
    protected function resolveHotelId(): ?int
    {
        return $this->booking_id ? Booking::withoutGlobalScopes()->whereKey($this->booking_id)->value('hotel_id') : null;
    }
}
