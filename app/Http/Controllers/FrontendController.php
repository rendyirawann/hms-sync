<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Review;
use App\Models\RoomType;
use App\Services\Pricing;
use App\Services\RoomAvailability;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Public marketplace: discover hotels, compare availability, open a hotel. */
class FrontendController extends Controller
{
    private const DATE_MESSAGES = ['check_in.after_or_equal' => 'Tanggal check-in tidak boleh sebelum hari ini.'];

    private const DATE_ATTRIBUTES = ['check_in' => 'tanggal check-in', 'check_out' => 'tanggal check-out', 'guests' => 'jumlah tamu'];

    public function __construct(private RoomAvailability $availability, private Pricing $pricing)
    {
    }

    public function index()
    {
        $hotels = Hotel::listed()
            ->withAvg(['reviews' => fn ($q) => $q->where('is_published', true)], 'rating')
            ->withCount(['reviews' => fn ($q) => $q->where('is_published', true)])
            ->withMin(['roomTypes' => fn ($q) => $q->where('is_active', true)], 'base_price')
            ->orderByDesc('rating')
            ->limit(12)
            ->get();

        $cities = Hotel::listed()->whereNotNull('city')
            ->selectRaw('city, count(*) as total')->groupBy('city')->orderByDesc('total')->limit(8)->get();

        $reviews = Review::with(['user:id,name', 'hotel:id,name,slug'])
            ->where('is_published', true)
            ->whereHas('hotel', fn ($q) => $q->listed())
            ->latest()
            ->limit(6)
            ->get();

        return view('frontend.landing', compact('hotels', 'cities', 'reviews'));
    }

    /** Hotels with a free room for the stay, cheapest first. */
    public function search(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in', 'before_or_equal:' . now()->addYear()->toDateString()],
            'guests' => ['nullable', 'integer', 'min:1', 'max:20'],
        ], self::DATE_MESSAGES, self::DATE_ATTRIBUTES + ['q' => 'tujuan']);

        $checkIn = Carbon::parse($data['check_in']);
        $checkOut = Carbon::parse($data['check_out']);
        $guests = (int) ($data['guests'] ?? 1);
        $term = trim((string) ($data['q'] ?? ''));

        $hotels = Hotel::listed()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('city', 'ilike', "%{$term}%")
                ->orWhere('name', 'ilike', "%{$term}%")
                ->orWhere('province', 'ilike', "%{$term}%")))
            ->with(['roomTypes' => fn ($q) => $q->where('is_active', true)->where('capacity', '>=', $guests)])
            ->withAvg(['reviews' => fn ($q) => $q->where('is_published', true)], 'rating')
            ->get();

        $typeIds = $hotels->flatMap(fn ($h) => $h->roomTypes->pluck('id'))->all();
        $free = $this->availability->forTypes($typeIds, $checkIn, $checkOut);

        $results = $hotels->map(function (Hotel $hotel) use ($free, $checkIn, $checkOut) {
            $offers = $hotel->roomTypes
                ->filter(fn ($t) => ($free[$t->id] ?? 0) > 0)
                ->map(fn ($t) => ['type' => $t, 'quote' => $this->pricing->quote($t, $checkIn, $checkOut), 'left' => $free[$t->id]])
                ->sortBy(fn ($o) => $o['quote']['total'])
                ->values();

            return ['hotel' => $hotel, 'cheapest' => $offers->first(), 'types' => $offers->count(), 'rooms_left' => $offers->sum('left')];
        })
            ->filter(fn ($r) => $r['cheapest'] !== null)
            ->sortBy(fn ($r) => $r['cheapest']['quote']['total'])
            ->values();

        return view('frontend.search', [
            'results' => $results,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'guests' => $guests,
            'q' => $term,
            'nights' => $checkIn->diffInDays($checkOut),
        ]);
    }

    /** One hotel: info, gallery, reviews and its rooms (priced when dates are given). */
    public function hotel(Request $request, Hotel $hotel)
    {
        $this->ensureVisible($hotel);

        $stay = null;
        if ($request->filled(['check_in', 'check_out'])) {
            $data = $request->validate([
                'check_in' => ['required', 'date', 'after_or_equal:today'],
                'check_out' => ['required', 'date', 'after:check_in', 'before_or_equal:' . now()->addYear()->toDateString()],
                'guests' => ['nullable', 'integer', 'min:1', 'max:20'],
            ], self::DATE_MESSAGES, self::DATE_ATTRIBUTES);
            $stay = [
                'check_in' => Carbon::parse($data['check_in']),
                'check_out' => Carbon::parse($data['check_out']),
                'guests' => (int) ($data['guests'] ?? 1),
            ];
        }

        $types = $hotel->roomTypes()->where('is_active', true)->with('images')->orderBy('base_price')->get();
        $free = $stay ? $this->availability->forTypes($types->modelKeys(), $stay['check_in'], $stay['check_out']) : [];

        $rooms = $types->map(fn (RoomType $type) => [
            'type' => $type,
            'from' => $this->pricing->startingFrom($type),
            'available' => $stay ? ($free[$type->id] ?? 0) : null,
            'quote' => $stay ? $this->pricing->quote($type, $stay['check_in'], $stay['check_out']) : null,
            'fits' => ! $stay || $type->capacity >= $stay['guests'],
        ]);

        $reviews = $hotel->reviews()->with('user:id,name')->where('is_published', true)->latest()->limit(8)->get();

        return view('frontend.hotel', [
            'hotel' => $hotel->load('images'),
            'rooms' => $rooms,
            'stay' => $stay,
            'reviews' => $reviews,
            'rating' => $hotel->reviews()->where('is_published', true)->selectRaw('count(*) as total, avg(rating) as average')->first(),
        ]);
    }

    public function roomType(Hotel $hotel, RoomType $roomType)
    {
        $this->ensureVisible($hotel);
        abort_unless($roomType->is_active, 404);

        $roomType->load(['images']);
        $reviews = $roomType->reviews()->with('user:id,name')->where('is_published', true)->latest()->limit(10)->get();

        return view('frontend.room', [
            'hotel' => $hotel,
            'roomType' => $roomType,
            'reviews' => $reviews,
            'average' => $reviews->avg('rating'),
            'fromPrice' => $this->pricing->startingFrom($roomType),
        ]);
    }

    /** Pending or suspended hotels are visible only to their own staff and the platform (preview). */
    private function ensureVisible(Hotel $hotel): void
    {
        if ($hotel->isBookable()) {
            return;
        }

        $user = auth()->user();
        abort_unless($user && ($user->hasRole('Superadmin') || (int) $user->hotel_id === $hotel->id), 404);
    }
}
