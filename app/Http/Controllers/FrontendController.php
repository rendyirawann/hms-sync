<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\RoomType;
use App\Services\RoomAvailability;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FrontendController extends Controller
{
    public function __construct(private RoomAvailability $availability)
    {
    }

    public function index()
    {
        $hotel = Hotel::current();
        $roomTypes = RoomType::where('is_active', true)
            ->orderBy('base_price')
            ->get(['id', 'name', 'capacity', 'base_price', 'amenities', 'image']);

        return view('frontend.landing', compact('hotel', 'roomTypes'));
    }

    /** Room availability for the requested stay. */
    public function search(Request $request)
    {
        $data = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:20'],
        ], [], ['check_in' => 'tanggal check-in', 'check_out' => 'tanggal check-out', 'guests' => 'jumlah tamu']);

        $checkIn = Carbon::parse($data['check_in']);
        $checkOut = Carbon::parse($data['check_out']);
        $guests = (int) ($data['guests'] ?? 1);

        $types = RoomType::where('is_active', true)->orderBy('base_price')->get();
        $free = $this->availability->forTypes($types->modelKeys(), $checkIn, $checkOut);

        $results = $types
            ->map(fn (RoomType $type) => [
                'type' => $type,
                'available' => $free[$type->id] ?? 0,
                'total' => $this->availability->quote($type, $checkIn, $checkOut),
                'fits' => $type->capacity >= $guests,
            ]);

        return view('frontend.search', [
            'hotel' => Hotel::current(),
            'results' => $results,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'guests' => $guests,
            'nights' => $checkIn->diffInDays($checkOut),
        ]);
    }
}
