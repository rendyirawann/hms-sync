<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Review;
use App\Models\RoomType;
use App\Services\BookingService;
use App\Services\Invoice;
use App\Services\Pricing;
use App\Services\RoomAvailability;
use App\Services\Tripay;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** The signed-in guest's own bookings. */
class BookingController extends Controller
{
    public function __construct(
        private RoomAvailability $availability,
        private Pricing $pricing,
        private BookingService $bookings,
    ) {
    }

    public function index(Request $request)
    {
        $bookings = $request->user()->bookings()
            ->with(['roomType:id,name,image', 'reservation.room:id,room_number', 'review:id,booking_id'])
            ->latest()
            ->paginate(10);

        return view('frontend.customer.bookings', ['bookings' => $bookings, 'hotel' => Hotel::current()]);
    }

    public function show(Request $request, Booking $booking, Tripay $tripay)
    {
        $this->authorizeOwner($request, $booking);
        $booking->load(['roomType', 'reservation.room', 'payments', 'charges', 'review', 'promoCode']);

        return view('frontend.customer.show', [
            'booking' => $booking,
            'hotel' => Hotel::current(),
            'channels' => $booking->status === 'Pending' ? $tripay->channels() : [],
            'tripayEnabled' => $tripay->enabled(),
        ]);
    }

    public function create(Request $request, RoomType $roomType)
    {
        abort_unless($roomType->is_active, 404);

        $data = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ]);

        $checkIn = Carbon::parse($data['check_in']);
        $checkOut = Carbon::parse($data['check_out']);

        $promo = null;
        $promoError = null;
        try {
            $promo = $this->bookings->resolvePromo($data['promo_code'] ?? null, $checkIn, $checkOut);
        } catch (ValidationException $e) {
            $promoError = $e->errors()['promo_code'][0] ?? null;
        }

        return view('frontend.customer.create', [
            'hotel' => Hotel::current(),
            'roomType' => $roomType->load('images'),
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'guests' => min((int) ($data['guests'] ?? 1), $roomType->capacity),
            'nights' => $checkIn->diffInDays($checkOut),
            'quote' => $this->pricing->quote($roomType, $checkIn, $checkOut, $promo),
            'promoCode' => $data['promo_code'] ?? null,
            'promoError' => $promoError,
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
            'promo_code' => ['nullable', 'string', 'max:40'],
        ], ['check_in.after_or_equal' => 'Tanggal check-in tidak boleh sebelum hari ini.'], [
            'check_in' => 'tanggal check-in', 'check_out' => 'tanggal check-out',
            'guests' => 'jumlah tamu', 'guest_phone' => 'nomor telepon', 'notes' => 'catatan', 'promo_code' => 'kode promo',
        ]);

        $booking = $this->bookings->create($roomType, $data, $request->user()->id, 'online');

        return redirect()->route('customer.bookings.show', $booking)
            ->with('success', 'Reservasi ' . $booking->booking_code . ' berhasil dibuat. Selesaikan pembayaran sebelum batas waktu.');
    }

    /** Pay the balance through a Tripay channel (VA, QRIS, e-wallet, retail). */
    public function payTripay(Request $request, Booking $booking, Tripay $tripay)
    {
        $this->authorizeOwner($request, $booking);
        abort_unless($tripay->enabled(), 404);

        $data = $request->validate(['channel' => ['required', 'string', 'max:30']], [], ['channel' => 'metode pembayaran']);

        if ($booking->status !== 'Pending') {
            return back()->with('error', 'Reservasi ini tidak sedang menunggu pembayaran.');
        }

        if (! collect($tripay->channels())->pluck('code')->contains($data['channel'])) {
            return back()->with('error', 'Metode pembayaran tidak tersedia.');
        }

        try {
            $payment = $tripay->createTransaction($booking, $data['channel']);
        } catch (RuntimeException $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        return $payment->checkout_url
            ? redirect()->away($payment->checkout_url)
            : redirect()->route('customer.bookings.show', $booking);
    }

    /** Manual bank transfer: guest uploads the receipt, the front desk verifies it. */
    public function uploadProof(Request $request, Booking $booking)
    {
        $this->authorizeOwner($request, $booking);

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ], [], ['proof' => 'bukti transfer']);

        if ($booking->status !== 'Pending') {
            return back()->with('error', 'Reservasi ini tidak sedang menunggu pembayaran.');
        }

        if ($booking->payments()->where('method', 'transfer')->where('status', 'pending')->exists()) {
            return back()->with('error', 'Bukti transfer sebelumnya masih diperiksa.');
        }

        $booking->load(['charges', 'payments']);

        // Stored on the private disk; only staff can open it through a route.
        $path = $request->file('proof')->store('payments', 'local');

        $booking->payments()->create([
            'method' => 'transfer',
            'amount' => $booking->balance_due,
            'status' => 'pending',
            'proof_path' => $path,
        ]);

        $this->bookings->announce($booking, 'proof_uploaded');

        return back()->with('success', 'Bukti transfer terkirim. Kami akan memverifikasi secepatnya.');
    }

    public function invoice(Request $request, Booking $booking, Invoice $invoice)
    {
        $this->authorizeOwner($request, $booking);

        return $invoice->download($booking);
    }

    public function cancel(Request $request, Booking $booking)
    {
        $this->authorizeOwner($request, $booking);

        if ($booking->status !== 'Pending') {
            return back()->with('error', 'Reservasi yang sudah dibayar/diproses tidak bisa dibatalkan sendiri. Silakan hubungi hotel.');
        }

        $this->bookings->release($booking, 'Cancelled', 'Dibatalkan oleh tamu', 'cancelled');

        return back()->with('success', 'Reservasi ' . $booking->booking_code . ' dibatalkan.');
    }

    public function review(Request $request, Booking $booking)
    {
        $this->authorizeOwner($request, $booking);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], [], ['rating' => 'rating', 'comment' => 'ulasan']);

        if ($booking->status !== 'Completed') {
            return back()->with('error', 'Ulasan bisa diberikan setelah check-out.');
        }

        Review::updateOrCreate(
            ['booking_id' => $booking->id],
            $data + ['user_id' => $booking->user_id, 'room_type_id' => $booking->room_type_id, 'is_published' => true]
        );

        return back()->with('success', 'Terima kasih atas ulasan Anda!');
    }

    private function authorizeOwner(Request $request, Booking $booking): void
    {
        // 404 rather than 403: do not confirm that someone else's booking exists.
        abort_unless($booking->user_id === $request->user()->id, 404);
    }
}
