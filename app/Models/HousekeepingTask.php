<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;

class HousekeepingTask extends Model
{
    use BelongsToHotel;

    public const TYPES = [
        'checkout' => 'Bersih setelah check-out',
        'stayover' => 'Bersih harian',
        'inspection' => 'Inspeksi',
        'maintenance' => 'Perbaikan',
    ];

    public const STATUSES = ['pending' => 'Menunggu', 'in_progress' => 'Dikerjakan', 'done' => 'Selesai'];

    protected $fillable = ['hotel_id', 'room_id', 'assigned_to', 'type', 'status', 'notes', 'started_at', 'finished_at', 'created_by'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (HousekeepingTask $task) {
            $room = $task->room()->withoutGlobalScopes()->first(['id', 'hotel_id', 'room_number', 'status']);
            if ($room) {
                \App\Events\Hms\RoomBoardUpdated::dispatch(
                    (int) $room->hotel_id, $room->id, $room->room_number, $room->status,
                    'tugas ' . strtolower(self::STATUSES[$task->status] ?? $task->status)
                );
            }
        });
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** hotel_id follows the parent room. */
    protected function resolveHotelId(): ?int
    {
        return $this->room_id ? Room::withoutGlobalScopes()->whereKey($this->room_id)->value('hotel_id') : null;
    }
}
