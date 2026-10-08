<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Services\RoomAvailability;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** The signed-in guest's own bookings. */
class BookingController extends Controller
{
    public function __construct(private RoomAvailability $availability)
    {
    }

    public function index(Request $request)
    {
        $bookings = $request->user()->bookings()->with(['roomType', 'reservation.room'])->latest()->paginate(10);

        return view('frontend.customer.bookings', ['bookings' => $bookings, 'hotel' => Hotel::current()]);
    }

    public function create(Request $request, RoomType $roomType)
    {
        abort_unless($roomType->is_active, 404);

        $data = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1'],
        ]);

        $checkIn = Carbon::parse($data['check_in']);
        $checkOut = Carbon::parse($data['check_out']);

        return view('frontend.customer.create', [
            'hotel' => Hotel::current(),
            'roomType' => $roomType,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'guests' => min((int) ($data['guests'] ?? 1), $roomType->capacity),
            'nights' => $checkIn->diffInDays($checkOut),
            'total' => $this->availability->quote($roomType, $checkIn, $checkOut),
            'available' => $this->availability->available($roomType, $checkIn, $checkOut),
        ]);
    }

    public function store(Request $request, RoomType $roomType)
    {
        abort_unless($roomType->is_active, 404);

        $data = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in', 'before_or_equal:' . now()->addYear()->toDateString()],
            'guests' => ['required', 'integer', 'min:1', 'max:' . $roomType->capacity],
            'guest_phone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'check_in' => 'tanggal check-in', 'check_out' => 'tanggal check-out',
            'guests' => 'jumlah tamu', 'guest_phone' => 'nomor telepon', 'notes' => 'catatan',
        ]);

        $checkIn = Carbon::parse($data['check_in']);
        $checkOut = Carbon::parse($data['check_out']);

        $booking = DB::transaction(function () use ($request, $roomType, $data, $checkIn, $checkOut) {
            // Serialise bookings of the same room type so two guests cannot
            // both take the last room.
            RoomType::whereKey($roomType->id)->lockForUpdate()->first();

            if ($this->availability->available($roomType, $checkIn, $checkOut) < 1) {
                return null;
            }

            return Booking::create([
                'user_id' => $request->user()->id,
                'room_type_id' => $roomType->id,
                'check_in_date' => $checkIn->toDateString(),
                'check_out_date' => $checkOut->toDateString(),
                'total_price' => $this->availability->quote($roomType, $checkIn, $checkOut),
                'booking_code' => $this->newCode(),
                'status' => 'Pending',
                'guests' => $data['guests'],
                'guest_phone' => $data['guest_phone'],
                'notes' => $data['notes'] ?? null,
            ]);
        });

        if (! $booking) {
            return back()->withInput()->with('error', 'Maaf, kamar tipe ini sudah penuh untuk tanggal tersebut.');
        }

        return redirect()->route('customer.bookings.index')
            ->with('success', 'Reservasi ' . $booking->booking_code . ' berhasil dibuat dan menunggu konfirmasi hotel.');
    }

    public function cancel(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 404);

        if ($booking->status !== 'Pending') {
            return back()->with('error', 'Reservasi yang sudah diproses hotel tidak bisa dibatalkan sendiri. Silakan hubungi hotel.');
        }

        $booking->update(['status' => 'Cancelled', 'cancel_reason' => 'Dibatalkan oleh tamu']);

        return back()->with('success', 'Reservasi ' . $booking->booking_code . ' dibatalkan.');
    }

    private function newCode(): string
    {
        do {
            $code = 'HMS-' . now()->format('ymd') . '-' . Str::upper(Str::random(5));
        } while (Booking::where('booking_code', $code)->exists());

        return $code;
    }
}
