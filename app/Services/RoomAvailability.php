<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Inventory is sold per room type: a booking reserves "one room of type X"
 * and a concrete room is only assigned at check-in. A type is available for
 * a stay when its sellable rooms outnumber the bookings already holding it
 * on any of those nights.
 */
class RoomAvailability
{
    public function available(RoomType $type, CarbonInterface $checkIn, CarbonInterface $checkOut): int
    {
        return $this->forTypes([$type->id], $checkIn, $checkOut)[$type->id] ?? 0;
    }

    /**
     * Free rooms per type for a stay, in two grouped queries regardless of
     * how many types are asked for.
     *
     * @param  iterable<int>  $typeIds
     * @return array<int, int>  room_type_id => rooms still free
     */
    public function forTypes(iterable $typeIds, CarbonInterface $checkIn, CarbonInterface $checkOut): array
    {
        $ids = Collection::make($typeIds)->values()->all();

        if ($ids === []) {
            return [];
        }

        // Rooms under maintenance are out of inventory.
        $sellable = Room::query()
            ->whereIn('room_type_id', $ids)
            ->where('status', '!=', 'Maintenance')
            ->groupBy('room_type_id')
            ->selectRaw('room_type_id, count(*) as total')
            ->pluck('total', 'room_type_id');

        $held = Booking::query()
            ->whereIn('room_type_id', $ids)
            ->whereIn('status', Booking::HOLDING)
            ->overlapping($checkIn->toDateString(), $checkOut->toDateString())
            ->groupBy('room_type_id')
            ->selectRaw('room_type_id, count(*) as total')
            ->pluck('total', 'room_type_id');

        $result = [];
        foreach ($ids as $id) {
            $result[$id] = max(0, (int) ($sellable[$id] ?? 0) - (int) ($held[$id] ?? 0));
        }

        return $result;
    }

    /** Physical rooms of the booked type that are free to hand over right now. */
    public function assignableRooms(Booking $booking)
    {
        return Room::query()
            ->where('room_type_id', $booking->room_type_id)
            ->where('status', 'Available')
            ->whereDoesntHave('activeReservation')
            ->orderBy('room_number')
            ->get(['id', 'room_number', 'floor']);
    }

    public function quote(RoomType $type, CarbonInterface $checkIn, CarbonInterface $checkOut): float
    {
        return (float) $type->base_price * max(1, $checkIn->diffInDays($checkOut));
    }
}
