<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    public const STATUSES = ['Available', 'Occupied', 'Maintenance', 'Cleaning'];

    protected $fillable = [
        'room_type_id', 'room_number', 'floor', 'status',
    ];

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function housekeepingTasks()
    {
        return $this->hasMany(HousekeepingTask::class);
    }

    public function openHousekeepingTask()
    {
        return $this->hasOne(HousekeepingTask::class)->where('status', '!=', 'done')->latestOfMany();
    }

    public function activeReservation()
    {
        return $this->hasOne(Reservation::class)->where('status', 'Active');
    }
}
