<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HotelImage extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id', 'path', 'caption', 'sort'];

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
