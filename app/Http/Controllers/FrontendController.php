<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\Review;
use App\Models\RoomType;
use App\Services\Pricing;
use App\Services\RoomAvailability;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FrontendController extends Controller
{
    public function __construct(private RoomAvailability $availability, private Pricing $pricing)
    {
    }

    public function index()
    {
        $hotel = Hotel::current();
        $roomTypes = RoomType::where('is_active', true)
            ->withAvg(['reviews' => fn ($q) => $q->where('is_published', true)], 'rating')
            ->orderBy('base_price')
            ->get(['id', 'name', 'capacity', 'base_price', 'amenities', 'image']);

        $fromPrice = $roomTypes->mapWithKeys(fn ($t) => [$t->id => $this->pricing->startingFrom($t)]);

        $reviews = Review::with(['user:id,name', 'roomType:id,name'])
            ->where('is_published', true)
            ->latest()
            ->limit(6)
            ->get();

        $rating = Review::where('is_published', true)->selectRaw('count(*) as total, avg(rating) as average')->first();
        $gallery = HotelImage::orderBy('sort')->limit(8)->get();

        return view('frontend.landing', compact('hotel', 'roomTypes', 'fromPrice', 'reviews', 'rating', 'gallery'));
    }

    /** Room availability for the requested stay. */
    public function search(Request $request)
    {
        $data = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in', 'before_or_equal:' . now()->addYear()->toDateString()],
            'guests' => ['nullable', 'integer', 'min:1', 'max:20'],
        ], [], ['check_in' => 'tanggal check-in', 'check_out' => 'tanggal check-out', 'guests' => 'jumlah tamu']);

        $checkIn = Carbon::parse($data['check_in']);
        $checkOut = Carbon::parse($data['check_out']);
        $guests = (int) ($data['guests'] ?? 1);

        $types = RoomType::where('is_active', true)->orderBy('base_price')->get();
        $free = $this->availability->forTypes($types->modelKeys(), $checkIn, $checkOut);

        $results = $types->map(fn (RoomType $type) => [
            'type' => $type,
            'available' => $free[$type->id] ?? 0,
            'quote' => $this->pricing->quote($type, $checkIn, $checkOut),
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

    public function roomType(RoomType $roomType)
    {
        abort_unless($roomType->is_active, 404);

        $roomType->load(['images']);
        $reviews = $roomType->reviews()->with('user:id,name')->where('is_published', true)->latest()->limit(10)->get();

        return view('frontend.room', [
            'hotel' => Hotel::current(),
            'roomType' => $roomType,
            'reviews' => $reviews,
            'average' => $reviews->avg('rating'),
            'fromPrice' => $this->pricing->startingFrom($roomType),
        ]);
    }
}
