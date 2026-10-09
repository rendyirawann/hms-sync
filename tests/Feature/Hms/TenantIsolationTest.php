<?php

namespace Tests\Feature\Hms;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Settlement;
use App\Services\SettlementService;

/** Hotel staff must never see or change another hotel's data. */
class TenantIsolationTest extends HmsTestCase
{
    private Hotel $other;

    private Booking $otherBooking;

    private Room $otherRoom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->other = Hotel::create(['name' => 'Hotel Lain', 'city' => 'Bali', 'address' => 'x', 'approval_status' => 'approved', 'status' => true]);
        $type = RoomType::create(['hotel_id' => $this->other->id, 'name' => 'Villa', 'capacity' => 2, 'base_price' => 900000, 'is_active' => true]);
        $this->otherRoom = Room::create(['room_type_id' => $type->id, 'room_number' => '101', 'status' => 'Available']);

        $this->actingAs($this->user('Customer'))->post(route('customer.bookings.store', $type), [
            'check_in' => today()->toDateString(), 'check_out' => today()->addDay()->toDateString(), 'guests' => 1, 'guest_phone' => '0811',
        ]);
        $this->otherBooking = Booking::withoutGlobalScopes()->where('hotel_id', $this->other->id)->firstOrFail();
    }

    public function test_child_rows_inherit_the_hotel(): void
    {
        $this->assertSame($this->other->id, $this->otherRoom->hotel_id, 'Room 101 may exist in both hotels.');
        $this->assertSame($this->other->id, $this->otherBooking->hotel_id);
    }

    public function test_staff_cannot_reach_another_hotels_records(): void
    {
        $staff = $this->user('Pegawai');
        $admin = $this->user('admin');

        $this->actingAs($staff)->get(route('hms.bookings.show', $this->otherBooking))->assertNotFound();
        $this->actingAs($staff)->ajax()->post(route('hms.bookings.check-in', $this->otherBooking), ['room_id' => $this->otherRoom->id])->assertNotFound();
        $this->actingAs($admin)->ajax()->put(route('hms.rooms.update', $this->otherRoom), [
            'room_type_id' => $this->otherRoom->room_type_id, 'room_number' => 'X', 'status' => 'Available',
        ])->assertNotFound();

        // Referencing the other hotel's room type is rejected by validation.
        $this->actingAs($admin)->ajax()->post(route('hms.rooms.store'), [
            'room_type_id' => $this->otherRoom->room_type_id, 'room_number' => 'HACK', 'status' => 'Available',
        ])->assertStatus(422)->assertJsonValidationErrors('room_type_id');

        $this->actingAs($staff)->get(route('hms.bookings.index'))->assertOk()->assertDontSee($this->otherBooking->booking_code);
        $this->actingAs($admin)->get(route('platform.hotels.index'))->assertForbidden();
    }

    public function test_platform_sees_every_hotel_and_can_enter_one(): void
    {
        $platform = $this->user('Superadmin');

        $this->actingAs($platform)->get(route('platform.hotels.index'))->assertOk()->assertSee('Hotel Uji')->assertSee('Hotel Lain');
        $this->actingAs($platform)->post(route('platform.hotels.enter', $this->other))->assertRedirect(route('hms.bookings.index'));
        $this->actingAs($platform)->get(route('hms.bookings.index'))->assertOk()->assertSee($this->otherBooking->booking_code);
    }

    public function test_pending_hotel_is_hidden_until_approved(): void
    {
        $this->other->update(['approval_status' => 'pending', 'status' => false]);

        $this->get('/')->assertOk()->assertDontSee('Hotel Lain');
        $this->get(route('hotels.show', $this->other))->assertNotFound();

        $this->actingAs($this->user('Superadmin'))->ajax()->post(route('platform.hotels.approve', $this->other))->assertOk();
        auth()->logout();
        $this->get(route('hotels.show', $this->other))->assertOk()->assertSee('Hotel Lain');
    }

    public function test_partner_registration_creates_pending_hotel_and_owner(): void
    {
        auth()->logout();

        $this->post(route('partner.register'), [
            'hotel_name' => 'Hotel Baru', 'city' => 'Malang', 'address' => 'Jl. Ijen 1', 'hotel_phone' => '0341',
            'name' => 'Pemilik', 'email' => 'pemilik@baru.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'agree' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $hotel = Hotel::where('name', 'Hotel Baru')->firstOrFail();
        $this->assertSame('pending', $hotel->approval_status);
        $this->assertSame($hotel->id, $hotel->owner->hotel_id);
        $this->assertTrue($hotel->owner->hasRole('admin'));
        $this->assertFalse($hotel->isBookable());
    }

    public function test_settlement_takes_commission_from_platform_collected_money(): void
    {
        $this->other->update(['commission_percent' => 15]);
        $booking = $this->otherBooking;
        $booking->update(['status' => 'Completed', 'check_out_date' => today()->subDay(), 'check_in_date' => today()->subDays(2), 'commission_percent' => 15]);
        $booking->payments()->create(['method' => 'tripay', 'amount' => $booking->total_price, 'status' => 'paid', 'paid_at' => now()]);

        $settlement = app(SettlementService::class)->create($this->other, today()->subDays(7), today());

        $roomRevenue = (float) $booking->room_total - (float) $booking->discount;
        $this->assertInstanceOf(Settlement::class, $settlement);
        $this->assertEqualsWithDelta($roomRevenue * 0.15, (float) $settlement->commission_amount, 0.01);
        $this->assertEqualsWithDelta((float) $booking->total_price - $roomRevenue * 0.15, (float) $settlement->payout_amount, 0.01);
        $this->assertSame($settlement->id, $booking->fresh()->settlement_id);

        // Never settled twice.
        $this->assertNull(app(SettlementService::class)->create($this->other, today()->subDays(7), today()));
    }
}
