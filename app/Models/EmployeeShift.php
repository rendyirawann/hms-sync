<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;

class EmployeeShift extends Model
{
    use BelongsToHotel;

    protected $fillable = [
        'hotel_id', 'user_id', 'shift_id', 'date', 'status', 'start_at', 'end_at',
    ];

    protected $casts = [
        'date' => 'date',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'Started' => 'primary',
            'Finished' => 'success',
            default => 'secondary',
        };
    }

    /** hotel_id follows the parent shift. */
    protected function resolveHotelId(): ?int
    {
        return $this->shift_id ? Shift::withoutGlobalScopes()->whereKey($this->shift_id)->value('hotel_id') : null;
    }
}
