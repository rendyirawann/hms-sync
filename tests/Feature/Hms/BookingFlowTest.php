<?php

namespace Tests\Feature\Hms;

use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\RateRule;
use App\Notifications\BookingNotification;
use Illuminate\Support\Facades\Notification;

class BookingFlowTest extends HmsTestCase
{
    private function book($user, array $overrides = [])
    {
        return $this->actingAs($user)->post(route('customer.bookings.store', $this->type), $overrides + [
            'check_in' => today()->addDays(10)->toDateString(),
            'check_out' => today()->addDays(12)->toDateString(),
            'guests' => 2,
            'guest_phone' => '0811',
        ]);
    }

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('Deluxe');
        $this->get(route('rooms.show', $this->type))->assertOk();
        $this->get(route('rooms.search', [
            'check_in' => today()->addDay()->toDateString(),
            'check_out' => today()->addDays(3)->toDateString(),
            'guests' => 2,
        ]))->assertOk()->assertSee('Pesan Sekarang');
    }

    public function test_booking_prices_with_service_and_tax(): void
    {
        $guest = $this->user('Customer');
        $this->book($guest)->assertRedirect();

        $booking = Booking::firstOrFail();
        // 2 nights x 500k = 1,000,000; service 5% = 50,000; tax 10% of 1,050,000 = 105,000.
        $this->assertEquals(1000000, (float) $booking->room_total);
        $this->assertEquals(50000, (float) $booking->service_charge);
        $this->assertEquals(105000, (float) $booking->tax);
        $this->assertEquals(1155000, (float) $booking->total_price);
        $this->assertSame('Pending', $booking->status);
        $this->assertNotNull($booking->payment_due_at);

        Notification::assertSentTo($guest, BookingNotification::class, fn ($n) => $n->event === 'created');
    }

    public function test_last_room_cannot_be_double_booked(): void
    {
        $this->book($this->user('Customer'))->assertSessionHasNoErrors();
        $this->book($this->user('Customer'))->assertSessionHasErrors('check_in');

        $this->assertSame(1, Booking::count());
    }

    public function test_weekend_rate_rule_and_promo_are_applied(): void
    {
        $friday = today()->next('Friday');
        RateRule::create(['name' => 'Weekend', 'days_of_week' => [5, 6], 'adjust_type' => 'percent', 'value' => 20, 'is_active' => true]);
        PromoCode::create(['code' => 'hemat10', 'type' => 'percent', 'value' => 10, 'is_active' => true]);

        $this->book($this->user('Customer'), [
            'check_in' => $friday->toDateString(),
            'check_out' => $friday->copy()->addDays(3)->toDateString(), // Fri, Sat, Sun
            'promo_code' => 'HEMAT10',
        ])->assertSessionHasNoErrors();

        $booking = Booking::firstOrFail();
        // 600k + 600k + 500k = 1,700,000; promo 10% = 170,000.
        $this->assertEquals(1700000, (float) $booking->room_total);
        $this->assertEquals(170000, (float) $booking->discount);
        $this->assertSame(1, PromoCode::first()->used_count);
    }

    public function test_invalid_promo_is_rejected(): void
    {
        PromoCode::create(['code' => 'LAMA', 'type' => 'amount', 'value' => 50000, 'valid_until' => today()->subDay(), 'is_active' => true]);

        $this->book($this->user('Customer'), ['promo_code' => 'LAMA'])->assertSessionHasErrors('promo_code');
        $this->assertSame(0, Booking::count());
    }

    public function test_unpaid_booking_expires_and_releases_room(): void
    {
        $guest = $this->user('Customer');
        $this->book($guest);
        Booking::query()->update(['payment_due_at' => now()->subMinute()]);

        $this->artisan('hms:expire-bookings')->assertSuccessful();

        $this->assertSame('Expired', Booking::first()->status);
        Notification::assertSentTo($guest, BookingNotification::class, fn ($n) => $n->event === 'expired');
        // The room is sellable again.
        $this->book($this->user('Customer'))->assertSessionHasNoErrors();
    }

    public function test_pending_transfer_proof_keeps_booking_alive(): void
    {
        $guest = $this->user('Customer');
        $this->book($guest);
        $booking = Booking::first();
        $booking->payments()->create(['method' => 'transfer', 'amount' => 1, 'status' => 'pending']);
        $booking->update(['payment_due_at' => now()->subMinute()]);

        $this->artisan('hms:expire-bookings');

        $this->assertSame('Pending', $booking->fresh()->status);
    }

    public function test_no_show_is_marked_after_check_in_day(): void
    {
        $booking = Booking::create([
            'user_id' => $this->user('Customer')->id, 'room_type_id' => $this->type->id,
            'check_in_date' => today()->subDay(), 'check_out_date' => today()->addDay(),
            'total_price' => 1, 'room_total' => 1, 'booking_code' => 'HMS-NOSHOW', 'status' => 'Confirmed',
        ]);

        $this->artisan('hms:mark-no-shows')->assertSuccessful();

        $this->assertSame('NoShow', $booking->fresh()->status);
    }

    public function test_guest_can_cancel_pending_booking_but_not_someone_elses(): void
    {
        $guest = $this->user('Customer');
        $this->book($guest);
        $booking = Booking::first();

        $this->actingAs($this->user('Customer'))->get(route('customer.bookings.show', $booking))->assertNotFound();
        $this->actingAs($guest)->get(route('customer.bookings.show', $booking))->assertOk()->assertSee($booking->booking_code);

        $this->actingAs($guest)->post(route('customer.bookings.cancel', $booking))->assertRedirect();
        $this->assertSame('Cancelled', $booking->fresh()->status);
    }
}
