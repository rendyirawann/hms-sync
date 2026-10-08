<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RoomTypeImage extends Model
{
    protected $fillable = ['room_type_id', 'path', 'sort'];

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
