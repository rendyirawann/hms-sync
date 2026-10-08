<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HotelImage extends Model
{
    protected $fillable = ['path', 'caption', 'sort'];

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
