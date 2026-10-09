<?php

namespace App\Http\Controllers\Backend\Platform;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Guests pay online into the platform's accounts, so the platform (not the
 * hotel) confirms manual bank-transfer receipts.
 */
class PaymentController extends Controller
{
    use RespondsWithAlert;

    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        $payments = Payment::query()
            ->with(['booking:id,booking_code,user_id,guest_name,hotel_id,total_price,check_in_date', 'booking.user:id,name', 'hotel:id,name', 'verifier:id,name'])
            ->where('method', 'transfer')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $pending = Payment::where('method', 'transfer')->where('status', 'pending')->count();

        return view('backend.platform.payments.index', compact('payments', 'status', 'pending'));
    }

    public function proof(Payment $payment)
    {
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response($payment->proof_path, null, [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function verify(Request $request, Payment $payment, BookingService $bookings)
    {
        if ($payment->status !== 'pending' || $payment->method !== 'transfer') {
            return $this->fail($request, 'Pembayaran ini sudah diproses.');
        }

        $payment->update(['verified_by' => $request->user()->id]);
        $bookings->markPaid($payment->booking, $payment);

        return $this->done($request, 'Transfer diterima. Booking ' . $payment->booking->booking_code . ' ditandai lunas.');
    }

    public function reject(Request $request, Payment $payment)
    {
        $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        if ($payment->status !== 'pending' || $payment->method !== 'transfer') {
            return $this->fail($request, 'Pembayaran ini sudah diproses.');
        }

        $payment->update([
            'status' => 'rejected',
            'verified_by' => $request->user()->id,
            'note' => $request->input('note') ?: 'Bukti transfer tidak valid',
        ]);

        return $this->done($request, 'Bukti transfer ditolak. Tamu bisa mengunggah ulang.');
    }
}
