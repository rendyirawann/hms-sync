<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'booking_id', 'room_id', 'actual_check_in', 'actual_check_out', 'door_passcode', 'status',
    ];

    protected $casts = [
        'actual_check_in' => 'datetime',
        'actual_check_out' => 'datetime',
    ];

    protected $hidden = ['door_passcode'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
