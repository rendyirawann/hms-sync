<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Reservation extends Model
{
    use LogsActivity, BelongsToHotel;

    protected $fillable = [
        'hotel_id', 'booking_id', 'room_id', 'actual_check_in', 'actual_check_out', 'door_passcode', 'status',
    ];

    protected $casts = [
        'actual_check_in' => 'datetime',
        'actual_check_out' => 'datetime',
        // Encrypted at rest with APP_KEY: a database leak does not open doors.
        'door_passcode' => 'encrypted',
    ];

    protected $hidden = ['door_passcode'];

    public function getActivitylogOptions(): LogOptions
    {
        // The passcode itself is never written to the audit log.
        return LogOptions::defaults()
            ->useLogName('reservation')
            ->logOnly(['room_id', 'status', 'actual_check_in', 'actual_check_out'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function moves()
    {
        return $this->hasMany(RoomMove::class)->latest();
    }

    /** hotel_id follows the parent booking. */
    protected function resolveHotelId(): ?int
    {
        return $this->booking_id ? Booking::withoutGlobalScopes()->whereKey($this->booking_id)->value('hotel_id') : null;
    }
}
