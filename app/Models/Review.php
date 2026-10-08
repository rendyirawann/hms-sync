<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = ['booking_id', 'user_id', 'room_type_id', 'rating', 'comment', 'is_published'];

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
}
