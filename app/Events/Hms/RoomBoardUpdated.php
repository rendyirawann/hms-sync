<?php

namespace App\Events\Hms;

use App\Models\Room;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A room changed status or its housekeeping task moved: refresh boards. */
class RoomBoardUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $roomId, public string $roomNumber, public string $status, public string $reason)
    {
    }

    public static function forRoom(Room $room, string $reason): self
    {
        return new self($room->id, $room->room_number, $room->status, $reason);
    }

    public function broadcastAs(): string
    {
        return 'room.updated';
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('hms.housekeeping')];
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'room' => $this->roomNumber,
            'status' => $this->status,
            'message' => 'Kamar ' . $this->roomNumber . ': ' . $this->reason,
        ];
    }
}
