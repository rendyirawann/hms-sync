<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousekeepingTask extends Model
{
    public const TYPES = [
        'checkout' => 'Bersih setelah check-out',
        'stayover' => 'Bersih harian',
        'inspection' => 'Inspeksi',
        'maintenance' => 'Perbaikan',
    ];

    public const STATUSES = ['pending' => 'Menunggu', 'in_progress' => 'Dikerjakan', 'done' => 'Selesai'];

    protected $fillable = ['room_id', 'assigned_to', 'type', 'status', 'notes', 'started_at', 'finished_at', 'created_by'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (HousekeepingTask $task) {
            $room = $task->room()->first(['id', 'room_number', 'status']);
            if ($room) {
                \App\Events\Hms\RoomBoardUpdated::dispatch(
                    $room->id, $room->room_number, $room->status,
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
}
