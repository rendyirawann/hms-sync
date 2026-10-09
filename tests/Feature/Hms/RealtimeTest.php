<?php

namespace Tests\Feature\Hms;

use App\Events\Hms\BookingUpdated;
use App\Events\Hms\RoomBoardUpdated;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

class RealtimeTest extends HmsTestCase
{
    public function test_booking_lifecycle_is_broadcast_without_the_door_code(): void
    {
        Event::fake([BookingUpdated::class, RoomBoardUpdated::class]);
        $guest = $this->user('Customer');

        $this->actingAs($guest)->post(route('customer.bookings.store', $this->type), [
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDay()->toDateString(),
            'guests' => 1,
            'guest_phone' => '0811',
        ]);
        $booking = Booking::firstOrFail();

        Event::assertDispatched(BookingUpdated::class, function (BookingUpdated $e) use ($guest) {
            $channels = collect($e->broadcastOn())->map->name->all();

            return $e->action === 'created'
                && in_array('private-hms.hotel.' . $e->booking->hotel_id . '.frontdesk', $channels, true)
                && in_array('private-App.Models.User.' . $guest->id, $channels, true);
        });

        $staff = $this->user('admin');
        $this->actingAs($staff)->ajax()->post(route('hms.bookings.paid', $booking), ['method' => 'cash'])->assertOk();
        $this->actingAs($staff)->ajax()->post(route('hms.bookings.check-in', $booking), ['room_id' => Room::value('id')])->assertOk();

        $passcode = $booking->fresh()->reservation->door_passcode;
        $this->assertMatchesRegularExpression('/^\d{6}$/', $passcode);

        Event::assertDispatched(BookingUpdated::class, function (BookingUpdated $e) use ($passcode) {
            return $e->action === 'checked_in' && ! str_contains(json_encode($e->broadcastWith()), $passcode);
        });
        // Room went Available -> Occupied.
        Event::assertDispatched(RoomBoardUpdated::class, fn ($e) => $e->status === 'Occupied');
    }

    public function test_private_user_channel_does_not_match_other_uuids(): void
    {
        $channels = Broadcast::driver()->getChannels();
        $authorize = $channels['App.Models.User.{id}'];

        // Two UUIDs that share a numeric prefix used to collide under (int) casting.
        $alice = new User(['name' => 'A']);
        $alice->id = '019e0b1d-0000-7000-8000-000000000001';
        $bobId = '019e0b1d-ffff-7000-8000-000000000002';

        $this->assertTrue($authorize($alice, $alice->id));
        $this->assertFalse($authorize($alice, $bobId));
    }

    public function test_frontdesk_channel_requires_booking_permission(): void
    {
        $authorize = Broadcast::driver()->getChannels()['hms.hotel.{hotelId}.frontdesk'];
        $other = \App\Models\Hotel::create(['name' => 'Hotel Lain', 'address' => 'x']);

        $this->assertTrue((bool) $authorize($this->user('Pegawai'), $this->hotel->id));
        $this->assertFalse((bool) $authorize($this->user('Pegawai'), $other->id), 'Staff never hear another hotel.');
        $this->assertFalse((bool) $authorize($this->user('Customer'), $this->hotel->id));
    }
}
