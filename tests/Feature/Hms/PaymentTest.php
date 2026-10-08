<?php

namespace Tests\Feature\Hms;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PaymentTest extends HmsTestCase
{
    private Booking $booking;

    private $guest;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hms.tripay.enabled' => true,
            'hms.tripay.api_key' => 'test-api',
            'hms.tripay.private_key' => 'test-private',
            'hms.tripay.merchant_code' => 'T0001',
        ]);

        $this->guest = $this->user('Customer');
        $this->actingAs($this->guest)->post(route('customer.bookings.store', $this->type), [
            'check_in' => today()->addDays(3)->toDateString(),
            'check_out' => today()->addDays(4)->toDateString(),
            'guests' => 1,
            'guest_phone' => '0811',
        ]);
        $this->booking = Booking::firstOrFail();
    }

    public function test_manual_transfer_proof_is_verified_by_staff(): void
    {
        Storage::fake('local');

        $this->actingAs($this->guest)->post(route('customer.bookings.proof', $this->booking), [
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertSessionHasNoErrors();

        $payment = Payment::firstOrFail();
        Storage::disk('local')->assertExists($payment->proof_path);

        // Only staff can open the receipt.
        $this->actingAs($this->guest)->get(route('hms.bookings.payments.proof', $payment))->assertForbidden();
        $staff = $this->user('admin');
        $this->actingAs($staff)->get(route('hms.bookings.payments.proof', $payment))->assertOk();

        $this->actingAs($staff)->ajax()->post(route('hms.bookings.payments.verify', $payment))->assertOk();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('Paid', $this->booking->fresh()->status);
    }

    public function test_tripay_checkout_and_signed_callback(): void
    {
        Http::fake([
            '*/merchant/payment-channel' => Http::response(['success' => true, 'data' => [
                ['code' => 'QRIS', 'name' => 'QRIS', 'group' => 'E-Wallet', 'active' => true, 'icon_url' => null],
            ]]),
            '*/transaction/create' => Http::response(['success' => true, 'data' => [
                'reference' => 'T0001REF1', 'checkout_url' => 'https://tripay.co.id/checkout/T0001REF1',
                'pay_code' => null, 'expired_time' => now()->addHour()->timestamp,
            ]]),
        ]);

        $this->actingAs($this->guest)->post(route('customer.bookings.pay', $this->booking), ['channel' => 'QRIS'])
            ->assertRedirect('https://tripay.co.id/checkout/T0001REF1');

        $payment = Payment::where('method', 'tripay')->firstOrFail();
        Http::assertSent(fn ($request) => str_contains($request->url(), 'transaction/create')
            && $request['signature'] === hash_hmac('sha256', 'T0001' . $payment->merchant_ref . (int) $payment->amount, 'test-private'));

        $body = json_encode([
            'reference' => 'T0001REF1',
            'merchant_ref' => $payment->merchant_ref,
            'status' => 'PAID',
            'total_amount' => (int) $payment->amount,
            'paid_at' => now()->timestamp,
        ]);

        // Forged signature is refused.
        $this->call('POST', route('payment.tripay.callback'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CALLBACK_SIGNATURE' => 'forged',
            'HTTP_X_CALLBACK_EVENT' => 'payment_status',
        ], $body)->assertForbidden();
        $this->assertSame('Pending', $this->booking->fresh()->status);

        // Valid signature marks the booking paid (no CSRF token needed).
        $this->call('POST', route('payment.tripay.callback'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CALLBACK_SIGNATURE' => hash_hmac('sha256', $body, 'test-private'),
            'HTTP_X_CALLBACK_EVENT' => 'payment_status',
        ], $body)->assertOk()->assertJson(['success' => true]);

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('Paid', $this->booking->fresh()->status);
    }

    public function test_customer_downloads_own_invoice(): void
    {
        $this->actingAs($this->guest)->get(route('customer.bookings.invoice', $this->booking))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
