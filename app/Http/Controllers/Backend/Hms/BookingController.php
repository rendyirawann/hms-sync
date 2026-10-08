<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\RoomAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Front-desk flow: Pending -> (Paid) -> Confirmed -> check-in (room +
 * door passcode) -> check-out -> Completed. Cancelling is allowed until the
 * guest has checked in.
 */
class BookingController extends Controller
{
    use RespondsWithAlert;

    public function __construct(private RoomAvailability $availability)
    {
    }

    public function index(Request $request)
    {
        $bookings = Booking::query()
            ->with(['user', 'roomType', 'reservation.room'])
            ->when($request->filled('status'), function ($q) use ($request) {
                $request->status === 'InHouse'
                    ? $q->whereHas('reservation', fn ($r) => $r->where('status', 'Active'))
                    : $q->where('status', $request->status);
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('booking_code', 'ilike', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term)));
            })
            ->when($request->filled('date'), fn ($q) => $q->whereDate('check_in_date', $request->date))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'Pending' => Booking::where('status', 'Pending')->count(),
            'arrivals' => Booking::whereIn('status', ['Paid', 'Confirmed'])->whereDate('check_in_date', today())->doesntHave('reservation')->count(),
            'InHouse' => Reservation::where('status', 'Active')->count(),
            'departures' => Reservation::where('status', 'Active')->whereHas('booking', fn ($b) => $b->whereDate('check_out_date', today()))->count(),
        ];

        return view('backend.hms.bookings.index', compact('bookings', 'counts'));
    }

    public function show(Booking $booking)
    {
        $booking->load(['user', 'roomType', 'reservation.room']);
        $rooms = $this->canCheckIn($booking) ? $this->availability->assignableRooms($booking) : collect();

        return view('backend.hms.bookings._show', compact('booking', 'rooms'));
    }

    public function markPaid(Request $request, Booking $booking)
    {
        if ($booking->status !== 'Pending') {
            return $this->fail($request, 'Hanya booking berstatus Pending yang bisa ditandai lunas.');
        }

        $booking->update(['status' => 'Paid']);

        return $this->done($request, 'Booking ' . $booking->booking_code . ' ditandai sudah dibayar.');
    }

    public function confirm(Request $request, Booking $booking)
    {
        if (! in_array($booking->status, ['Pending', 'Paid'], true)) {
            return $this->fail($request, 'Booking ini tidak bisa dikonfirmasi lagi.');
        }

        $booking->update(['status' => 'Confirmed']);

        return $this->done($request, 'Booking ' . $booking->booking_code . ' berhasil dikonfirmasi.');
    }

    public function cancel(Request $request, Booking $booking)
    {
        $request->validate(['cancel_reason' => ['nullable', 'string', 'max:255']]);

        if (! in_array($booking->status, Booking::HOLDING, true) || $booking->reservation) {
            return $this->fail($request, 'Booking yang sudah check-in, selesai, atau batal tidak bisa dibatalkan.');
        }

        $booking->update([
            'status' => 'Cancelled',
            'cancel_reason' => $request->input('cancel_reason') ?: 'Dibatalkan oleh hotel',
        ]);

        return $this->done($request, 'Booking ' . $booking->booking_code . ' dibatalkan.');
    }

    public function checkIn(Request $request, Booking $booking)
    {
        $request->validate(['room_id' => ['required', Rule::exists('rooms', 'id')->where('room_type_id', $booking->room_type_id)]], [], ['room_id' => 'kamar']);

        if (! $this->canCheckIn($booking)) {
            return $this->fail($request, 'Booking harus sudah dikonfirmasi/dibayar dan tanggal hari ini masuk periode menginap.');
        }

        $reservation = DB::transaction(function () use ($request, $booking) {
            // Lock the room so two clerks cannot hand out the same key.
            $room = Room::whereKey($request->room_id)->lockForUpdate()->first();

            if ($room->status !== 'Available' || $room->activeReservation()->exists()) {
                return null;
            }

            $reservation = Reservation::create([
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'actual_check_in' => now(),
                'door_passcode' => $this->newPasscode(),
                'status' => 'Active',
            ]);

            $room->update(['status' => 'Occupied']);
            $booking->update(['status' => 'Confirmed']);

            return $reservation;
        });

        if (! $reservation) {
            return $this->fail($request, 'Kamar tersebut baru saja dipakai / tidak tersedia. Pilih kamar lain.');
        }

        return $this->done(
            $request,
            'Check-in berhasil. Kamar ' . $reservation->room->room_number . ', passcode pintu: ' . $reservation->door_passcode
        );
    }

    public function checkOut(Request $request, Booking $booking)
    {
        $reservation = $booking->reservation;

        if (! $reservation || $reservation->status !== 'Active') {
            return $this->fail($request, 'Tamu belum check-in atau sudah check-out.');
        }

        DB::transaction(function () use ($booking, $reservation) {
            $reservation->update([
                'status' => 'Completed',
                'actual_check_out' => now(),
                'door_passcode' => null, // the code stops working at check-out
            ]);
            $reservation->room->update(['status' => 'Cleaning']);
            $booking->update(['status' => 'Completed']);
        });

        return $this->done($request, 'Check-out berhasil. Kamar ' . $reservation->room->room_number . ' masuk antrean pembersihan.');
    }

    public function regeneratePasscode(Request $request, Booking $booking)
    {
        $reservation = $booking->reservation;

        if (! $reservation || $reservation->status !== 'Active') {
            return $this->fail($request, 'Passcode hanya bisa dibuat ulang untuk tamu yang sedang menginap.');
        }

        $reservation->update(['door_passcode' => $this->newPasscode()]);

        return $this->done($request, 'Passcode baru: ' . $reservation->door_passcode);
    }

    private function canCheckIn(Booking $booking): bool
    {
        return in_array($booking->status, ['Paid', 'Confirmed'], true)
            && ! $booking->reservation
            && $booking->check_in_date->lte(today())
            && $booking->check_out_date->gt(today());
    }

    /** 6-digit door code, unique among rooms currently occupied. */
    private function newPasscode(): string
    {
        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Reservation::where('status', 'Active')->where('door_passcode', $code)->exists());

        return $code;
    }
}
