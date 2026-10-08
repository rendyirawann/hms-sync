<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Tripay closed-payment integration.
 * Docs: https://tripay.co.id/developer
 */
class Tripay
{
    public function enabled(): bool
    {
        return (bool) config('hms.tripay.enabled')
            && filled(config('hms.tripay.private_key'))
            && filled(config('hms.tripay.merchant_code'));
    }

    private function baseUrl(): string
    {
        return config('hms.tripay.mode') === 'production'
            ? 'https://tripay.co.id/api'
            : 'https://tripay.co.id/api-sandbox';
    }

    private function http()
    {
        return Http::withToken((string) config('hms.tripay.api_key'))
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 300, throw: false);
    }

    /** Active payment channels (cached 1 hour). */
    public function channels(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return Cache::remember('tripay.channels.' . config('hms.tripay.mode'), 3600, function () {
            $response = $this->http()->get($this->baseUrl() . '/merchant/payment-channel');

            if (! $response->successful() || ! $response->json('success')) {
                return [];
            }

            return collect($response->json('data'))
                ->where('active', true)
                ->map(fn ($c) => [
                    'code' => $c['code'],
                    'name' => $c['name'],
                    'group' => $c['group'] ?? '',
                    'icon' => $c['icon_url'] ?? null,
                ])
                ->values()
                ->all();
        });
    }

    /** Create a Tripay invoice for the outstanding balance of a booking. */
    public function createTransaction(Booking $booking, string $channel): Payment
    {
        $booking->loadMissing(['user', 'roomType', 'charges', 'payments']);
        $amount = (int) round($booking->balance_due);

        if ($amount < 1) {
            throw new RuntimeException('Tidak ada tagihan yang perlu dibayar.');
        }

        $merchantRef = $booking->booking_code . '-' . Str::upper(Str::random(4));
        $expiresAt = now()->addMinutes((int) config('hms.tripay.expiry_minutes', 120));
        if ($booking->payment_due_at && $booking->payment_due_at->lt($expiresAt)) {
            $expiresAt = $booking->payment_due_at->copy();
        }

        $payload = [
            'method' => $channel,
            'merchant_ref' => $merchantRef,
            'amount' => $amount,
            'customer_name' => $booking->guest_display_name,
            'customer_email' => $booking->guest_contact_email ?: 'guest@' . parse_url(config('app.url'), PHP_URL_HOST),
            'customer_phone' => $booking->guest_phone ?: '0800000000',
            'order_items' => [[
                'sku' => 'RT' . $booking->room_type_id,
                'name' => Str::limit($booking->roomType->name . ' ' . $booking->nights . ' malam', 60, ''),
                'price' => $amount,
                'quantity' => 1,
            ]],
            'return_url' => route('customer.bookings.show', $booking),
            'expired_time' => $expiresAt->timestamp,
            'signature' => hash_hmac(
                'sha256',
                config('hms.tripay.merchant_code') . $merchantRef . $amount,
                (string) config('hms.tripay.private_key')
            ),
        ];

        $response = $this->http()->post($this->baseUrl() . '/transaction/create', $payload);

        if (! $response->successful() || ! $response->json('success')) {
            throw new RuntimeException('Gagal membuat tagihan Tripay: ' . ($response->json('message') ?? 'HTTP ' . $response->status()));
        }

        $data = $response->json('data');

        // Older unpaid Tripay invoices for this booking are superseded.
        $booking->payments()->where('method', 'tripay')->where('status', 'pending')->update(['status' => 'expired']);

        return $booking->payments()->create([
            'method' => 'tripay',
            'channel' => $channel,
            'amount' => $amount,
            'status' => 'pending',
            'reference' => $data['reference'],
            'merchant_ref' => $merchantRef,
            'checkout_url' => $data['checkout_url'] ?? null,
            'payload' => [
                'pay_code' => $data['pay_code'] ?? null,
                'qr_url' => $data['qr_url'] ?? null,
                'payment_name' => $data['payment_name'] ?? null,
                'instructions' => $data['instructions'] ?? [],
            ],
            'expires_at' => isset($data['expired_time']) ? now()->setTimestamp($data['expired_time']) : $expiresAt,
        ]);
    }

    /** Callback authenticity: HMAC-SHA256 of the raw body with the private key. */
    public function validCallbackSignature(string $rawBody, ?string $signature): bool
    {
        if (! $this->enabled() || blank($signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, (string) config('hms.tripay.private_key')), $signature);
    }
}
