<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeShift extends Model
{
    protected $fillable = [
        'user_id', 'shift_id', 'date', 'status', 'start_at', 'end_at',
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
}
