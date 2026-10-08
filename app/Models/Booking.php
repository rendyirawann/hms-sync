<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    public const STATUSES = ['Pending', 'Paid', 'Confirmed', 'Cancelled', 'Completed'];

    /** Bookings in these states still hold inventory. */
    public const HOLDING = ['Pending', 'Paid', 'Confirmed'];

    protected $fillable = [
        'user_id', 'room_type_id', 'check_in_date', 'check_out_date', 'total_price', 'booking_code', 'status',
        'guests', 'guest_phone', 'notes', 'cancel_reason',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'total_price' => 'decimal:2',
        'guests' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservation()
    {
        return $this->hasOne(Reservation::class);
    }

    /** Bookings whose stay overlaps [checkIn, checkOut). */
    public function scopeOverlapping(Builder $query, $checkIn, $checkOut): Builder
    {
        return $query->where('check_in_date', '<', $checkOut)->where('check_out_date', '>', $checkIn);
    }

    public function getNightsAttribute(): int
    {
        return (int) $this->check_in_date->diffInDays($this->check_out_date);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'Pending' => 'warning',
            'Paid' => 'info',
            'Confirmed' => 'primary',
            'Completed' => 'success',
            default => 'danger',
        };
    }

    public function getIsCheckedInAttribute(): bool
    {
        return $this->reservation?->status === 'Active';
    }
}
