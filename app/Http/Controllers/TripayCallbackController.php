<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\BookingService;
use App\Services\Tripay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tripay server-to-server callback. Authenticated by the HMAC signature in
 * X-Callback-Signature (CSRF is exempt for this path). Must answer
 * {"success": true} or Tripay keeps retrying.
 */
class TripayCallbackController extends Controller
{
    public function __invoke(Request $request, Tripay $tripay, BookingService $bookings): JsonResponse
    {
        $raw = $request->getContent();

        if (! $tripay->validCallbackSignature($raw, $request->header('X-Callback-Signature'))) {
            Log::warning('tripay.callback.bad-signature', ['ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        if ($request->header('X-Callback-Event') !== 'payment_status') {
            return response()->json(['success' => false, 'message' => 'Unrecognized event'], 400);
        }

        $data = json_decode($raw, true) ?: [];
        $status = strtoupper((string) ($data['status'] ?? ''));

        $payment = Payment::with('booking')
            ->where('merchant_ref', $data['merchant_ref'] ?? '')
            ->where('reference', $data['reference'] ?? '')
            ->first();

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        // Idempotent: Tripay may retry the same callback.
        if ($payment->status === 'paid') {
            return response()->json(['success' => true]);
        }

        DB::transaction(function () use ($payment, $status, $data, $bookings) {
            $payment = Payment::with('booking')->whereKey($payment->id)->lockForUpdate()->first();

            if ($status === 'PAID') {
                // total_amount includes Tripay's customer fee; what we billed is amount.
                if ((int) ($data['total_amount'] ?? 0) < (int) round((float) $payment->amount)) {
                    Log::warning('tripay.callback.amount-mismatch', ['payment' => $payment->id, 'total_amount' => $data['total_amount'] ?? null]);
                }

                $payment->paid_at = isset($data['paid_at']) ? now()->setTimestamp((int) $data['paid_at']) : now();
                $payment->payload = array_merge($payment->payload ?? [], ['callback' => $data]);
                $payment->save();
                $bookings->markPaid($payment->booking, $payment);
            } elseif ($status === 'EXPIRED') {
                $payment->update(['status' => 'expired']);
            } elseif (in_array($status, ['FAILED', 'REFUND'], true)) {
                $payment->update(['status' => 'failed', 'note' => $status]);
            }
        });

        return response()->json(['success' => true]);
    }
}
