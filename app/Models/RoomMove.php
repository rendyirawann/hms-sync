<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomMove extends Model
{
    protected $fillable = ['reservation_id', 'from_room_id', 'to_room_id', 'reason', 'moved_by'];

    public function fromRoom()
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    public function toRoom()
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }
}
