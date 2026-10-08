<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Verifying manual bank-transfer receipts uploaded by guests. */
class PaymentController extends Controller
{
    use RespondsWithAlert;

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

        return $this->done($request, 'Bukti transfer diterima. Booking ' . $payment->booking->booking_code . ' ditandai lunas.');
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
