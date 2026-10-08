<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\BookingService;
use App\Services\Invoice;
use App\Services\RoomAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Front-desk flow: Pending -> (Paid) -> Confirmed -> check-in (room +
 * door passcode) -> check-out -> Completed. Cancelling is allowed until the
 * guest has checked in. All state changes go through BookingService.
 */
class BookingController extends Controller
{
    use RespondsWithAlert;

    public function __construct(private RoomAvailability $availability, private BookingService $bookings)
    {
    }

    public function index(Request $request)
    {
        $bookings = Booking::query()
            ->with(['user:id,name,email', 'roomType:id,name', 'reservation:id,booking_id,room_id,status', 'reservation.room:id,room_number'])
            ->when($request->filled('status'), function ($q) use ($request) {
                $request->status === 'InHouse'
                    ? $q->whereHas('reservation', fn ($r) => $r->where('status', 'Active'))
                    : $q->where('status', $request->status);
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('booking_code', 'ilike', $term)
                    ->orWhere('guest_name', 'ilike', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term)));
            })
            ->when($request->filled('date'), fn ($q) => $q->whereDate('check_in_date', $request->date))
            ->when($request->boolean('needs_verification'), fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('method', 'transfer')->where('status', 'pending')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'Pending' => Booking::where('status', 'Pending')->count(),
            'verify' => Booking::whereHas('payments', fn ($p) => $p->where('method', 'transfer')->where('status', 'pending'))->count(),
            'arrivals' => Booking::whereIn('status', ['Paid', 'Confirmed'])->whereDate('check_in_date', today())->doesntHave('reservation')->count(),
            'InHouse' => Reservation::where('status', 'Active')->count(),
        ];

        $roomTypes = RoomType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'capacity']);

        return view('backend.hms.bookings.index', compact('bookings', 'counts', 'roomTypes'));
    }

    public function show(Booking $booking)
    {
        $booking->load(['user', 'roomType', 'reservation.room', 'reservation.moves.fromRoom', 'reservation.moves.toRoom', 'payments.verifier', 'charges', 'promoCode']);

        $rooms = $this->canCheckIn($booking) ? $this->availability->assignableRooms($booking) : collect();
        $moveTargets = $booking->is_checked_in
            ? Room::where('status', 'Available')->whereDoesntHave('activeReservation')->with('roomType:id,name')->orderBy('room_number')->get(['id', 'room_number', 'room_type_id'])
            : collect();

        return view('backend.hms.bookings._show', compact('booking', 'rooms', 'moveTargets'));
    }

    /** Walk-in guest at the desk: no account needed, optionally checked in right away. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'room_type_id' => ['required', Rule::exists('room_types', 'id')->where('is_active', true)],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:30'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ], ['check_in.after_or_equal' => 'Tanggal check-in tidak boleh sebelum hari ini.'], [
            'room_type_id' => 'tipe kamar', 'check_in' => 'tanggal check-in', 'check_out' => 'tanggal check-out',
            'guests' => 'jumlah tamu', 'guest_name' => 'nama tamu', 'guest_phone' => 'telepon', 'guest_email' => 'email',
        ]);

        $type = RoomType::findOrFail($data['room_type_id']);
        if ($data['guests'] > $type->capacity) {
            return $this->fail($request, "Kapasitas {$type->name} maksimal {$type->capacity} tamu.");
        }

        $booking = $this->bookings->create($type, $data, null, 'walkin', $request->user()->id);
        $this->bookings->confirm($booking);

        return $this->done($request, 'Booking walk-in ' . $booking->booking_code . ' dibuat. Lanjutkan pembayaran & check-in dari tombol Kelola.');
    }

    public function markPaid(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['cash', 'transfer'])],
            'amount' => ['nullable', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if (! in_array($booking->status, Booking::HOLDING, true) && ! $booking->is_checked_in) {
            return $this->fail($request, 'Booking ini tidak bisa menerima pembayaran.');
        }

        $booking->load(['charges', 'payments']);
        $amount = (float) ($data['amount'] ?? $booking->balance_due);
        if ($amount <= 0) {
            return $this->fail($request, 'Tidak ada tagihan tersisa.');
        }

        $payment = $booking->payments()->create([
            'method' => $data['method'],
            'amount' => $amount,
            'status' => 'pending',
            'verified_by' => $request->user()->id,
            'note' => $data['note'] ?? 'Dicatat di front desk',
        ]);
        $this->bookings->markPaid($booking, $payment);

        return $this->done($request, 'Pembayaran Rp ' . number_format($amount, 0, ',', '.') . ' dicatat untuk ' . $booking->booking_code . '.');
    }

    public function confirm(Request $request, Booking $booking)
    {
        if (! in_array($booking->status, ['Pending', 'Paid'], true)) {
            return $this->fail($request, 'Booking ini tidak bisa dikonfirmasi lagi.');
        }

        $this->bookings->confirm($booking);

        return $this->done($request, 'Booking ' . $booking->booking_code . ' berhasil dikonfirmasi.');
    }

    public function cancel(Request $request, Booking $booking)
    {
        $request->validate(['cancel_reason' => ['nullable', 'string', 'max:255']]);

        if (! in_array($booking->status, Booking::HOLDING, true) || $booking->reservation) {
            return $this->fail($request, 'Booking yang sudah check-in, selesai, atau batal tidak bisa dibatalkan.');
        }

        $this->bookings->release($booking, 'Cancelled', $request->input('cancel_reason') ?: 'Dibatalkan oleh hotel', 'cancelled');

        return $this->done($request, 'Booking ' . $booking->booking_code . ' dibatalkan.');
    }

    public function checkIn(Request $request, Booking $booking)
    {
        $request->validate(['room_id' => ['required', 'integer']], [], ['room_id' => 'kamar']);

        if (! $this->canCheckIn($booking)) {
            return $this->fail($request, 'Booking harus sudah dikonfirmasi/dibayar dan tanggal hari ini masuk periode menginap.');
        }

        $reservation = $this->bookings->checkIn($booking, (int) $request->room_id);

        return $this->done($request, $this->withLockWarning('Check-in berhasil. Kamar ' . $reservation->room->room_number . ', passcode pintu: ' . $reservation->door_passcode));
    }

    public function checkOut(Request $request, Booking $booking)
    {
        $booking->load(['reservation.room', 'charges', 'payments']);

        if (! $booking->is_checked_in) {
            return $this->fail($request, 'Tamu belum check-in atau sudah check-out.');
        }

        if ($booking->balance_due > 0 && ! $request->boolean('force')) {
            return $this->fail($request, 'Masih ada tagihan Rp ' . number_format($booking->balance_due, 0, ',', '.') . '. Catat pembayaran dulu sebelum check-out.');
        }

        $room = $booking->reservation->room->room_number;
        $this->bookings->checkOut($booking, $request->user()->id);

        return $this->done($request, $this->withLockWarning('Check-out berhasil. Kamar ' . $room . ' masuk antrean housekeeping.'));
    }

    public function regeneratePasscode(Request $request, Booking $booking)
    {
        if (! $booking->is_checked_in) {
            return $this->fail($request, 'Passcode hanya bisa dibuat ulang untuk tamu yang sedang menginap.');
        }

        return $this->done($request, $this->withLockWarning('Passcode baru: ' . $this->bookings->regeneratePasscode($booking->reservation)));
    }

    public function extend(Request $request, Booking $booking)
    {
        $request->validate(['check_out' => ['required', 'date']], [], ['check_out' => 'tanggal check-out baru']);

        if (! $booking->is_checked_in) {
            return $this->fail($request, 'Perpanjangan hanya untuk tamu yang sedang menginap.');
        }

        $amount = $this->bookings->extend($booking, $request->check_out, $request->user()->id);

        return $this->done($request, 'Masa inap diperpanjang. Tagihan tambahan Rp ' . number_format($amount, 0, ',', '.') . ' masuk ke folio.');
    }

    public function move(Request $request, Booking $booking)
    {
        $request->validate([
            'room_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [], ['room_id' => 'kamar tujuan']);

        if (! $booking->is_checked_in) {
            return $this->fail($request, 'Pindah kamar hanya untuk tamu yang sedang menginap.');
        }

        $to = $this->bookings->moveRoom($booking, (int) $request->room_id, $request->reason, $request->user()->id);

        return $this->done($request, $this->withLockWarning('Tamu dipindah ke kamar ' . $to->room_number . '. Passcode tetap sama.'));
    }

    public function addCharge(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(BookingCharge::CATEGORIES))],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ], [], ['category' => 'kategori', 'description' => 'keterangan', 'quantity' => 'jumlah', 'unit_price' => 'harga satuan']);

        if (! $booking->is_checked_in) {
            return $this->fail($request, 'Tagihan tambahan hanya untuk tamu yang sedang menginap.');
        }

        $booking->charges()->create($data + [
            'amount' => $data['quantity'] * $data['unit_price'],
            'created_by' => $request->user()->id,
        ]);

        return $this->done($request, 'Tagihan ditambahkan ke folio.');
    }

    public function removeCharge(Request $request, Booking $booking, BookingCharge $charge)
    {
        abort_unless($charge->booking_id === $booking->id, 404);

        if (! $booking->is_checked_in || $charge->category === 'extend') {
            return $this->fail($request, 'Tagihan ini tidak bisa dihapus.');
        }

        $charge->delete();

        return $this->done($request, 'Tagihan dihapus.');
    }

    public function invoice(Booking $booking, Invoice $invoice)
    {
        return $invoice->download($booking);
    }

    private function withLockWarning(string $message): string
    {
        return $this->bookings->lockWarning ? $message . "\n\n⚠ " . $this->bookings->lockWarning : $message;
    }

    private function canCheckIn(Booking $booking): bool
    {
        return in_array($booking->status, ['Paid', 'Confirmed'], true)
            && ! $booking->reservation
            && $booking->check_in_date->lte(today())
            && $booking->check_out_date->gt(today());
    }
}
