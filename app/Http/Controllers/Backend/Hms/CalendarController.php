<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

/**
 * Occupancy grid. Rows: physical rooms (who is in them) and, per room type,
 * how many rooms are still unsold each night (bookings are sold per type
 * and only get a room number at check-in).
 */
class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $start = Carbon::parse($request->input('start', today()->toDateString()))->startOfDay();
        $days = 14;
        $end = $start->copy()->addDays($days);
        $dates = collect(CarbonPeriod::create($start, $end->copy()->subDay()));

        $types = RoomType::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $rooms = Room::with('roomType:id,name')->orderBy('room_number')->get(['id', 'room_number', 'room_type_id', 'status']);
        $sellable = $rooms->where('status', '!=', 'Maintenance')->countBy('room_type_id');

        $bookings = Booking::query()
            ->with(['reservation:id,booking_id,room_id,status', 'user:id,name'])
            ->whereIn('status', ['Pending', 'Paid', 'Confirmed', 'Completed'])
            ->overlapping($start->toDateString(), $end->toDateString())
            ->get(['id', 'user_id', 'guest_name', 'room_type_id', 'check_in_date', 'check_out_date', 'status', 'booking_code']);

        // Room rows: stays with an assigned room.
        $byRoom = [];
        // Type rows: holding bookings per night.
        $held = [];

        foreach ($bookings as $booking) {
            foreach (CarbonPeriod::create($booking->check_in_date, $booking->check_out_date->copy()->subDay()) as $night) {
                if ($night->lt($start) || $night->gte($end)) {
                    continue;
                }
                $key = $night->toDateString();

                if ($booking->reservation) {
                    $byRoom[$booking->reservation->room_id][$key] = $booking;
                }
                if (in_array($booking->status, Booking::HOLDING, true)) {
                    $held[$booking->room_type_id][$key] = ($held[$booking->room_type_id][$key] ?? 0) + 1;
                }
            }
        }

        return view('backend.hms.calendar.index', compact('start', 'dates', 'types', 'rooms', 'sellable', 'byRoom', 'held'));
    }
}
