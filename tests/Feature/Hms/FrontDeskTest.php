<?php

namespace Tests\Feature\Hms;

use App\Models\Booking;
use App\Models\HousekeepingTask;
use App\Models\Room;
use App\Notifications\BookingNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class FrontDeskTest extends HmsTestCase
{
    private function walkIn($staff, array $overrides = []): Booking
    {
        $this->actingAs($staff)->ajax()->post(route('hms.bookings.store'), $overrides + [
            'room_type_id' => $this->type->id,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDay()->toDateString(),
            'guests' => 1,
            'guest_name' => 'Budi Walk-in',
            'guest_phone' => '0812',
            'guest_email' => 'budi@example.test',
        ])->assertOk();

        return Booking::latest('id')->firstOrFail();
    }

    public function test_full_walk_in_stay(): void
    {
        $staff = $this->user('Pegawai');
        $booking = $this->walkIn($staff);

        $this->assertSame('walkin', $booking->source);
        $this->assertNull($booking->user_id);
        $this->assertSame('Confirmed', $booking->status);
        $this->assertNull($booking->payment_due_at);
        Notification::assertSentOnDemand(BookingNotification::class);

        // Detail modal renders.
        $this->actingAs($staff)->get(route('hms.bookings.show', $booking))->assertOk()->assertSee('Budi Walk-in');

        // Pay at the desk.
        $this->actingAs($staff)->ajax()->post(route('hms.bookings.paid', $booking), ['method' => 'cash'])->assertOk();
        $this->assertEquals(0, $booking->fresh()->load(['charges', 'payments'])->balance_due);

        // Check in.
        $room = Room::where('room_number', '101')->first();
        $this->actingAs($staff)->ajax()->post(route('hms.bookings.check-in', $booking), ['room_id' => $room->id])->assertOk();
        $reservation = $booking->fresh()->reservation;
        $this->assertMatchesRegularExpression('/^\d{6}$/', $reservation->door_passcode);
        $this->assertSame('Occupied', $room->fresh()->status);

        // The door code is encrypted at rest.
        $raw = DB::table('reservations')->where('id', $reservation->id)->value('door_passcode');
        $this->assertNotSame($reservation->door_passcode, $raw);

        // Folio charge blocks check-out until settled.
        $this->actingAs($staff)->ajax()->post(route('hms.bookings.charges.store', $booking), [
            'category' => 'minibar', 'description' => 'Air mineral', 'quantity' => 2, 'unit_price' => 10000,
        ])->assertOk();
        $this->actingAs($staff)->ajax()->post(route('hms.bookings.check-out', $booking))->assertStatus(422);

        $this->actingAs($staff)->ajax()->post(route('hms.bookings.paid', $booking), ['method' => 'cash'])->assertOk();
        $this->actingAs($staff)->ajax()->post(route('hms.bookings.check-out', $booking))->assertOk();

        $this->assertSame('Completed', $booking->fresh()->status);
        $this->assertNull($reservation->fresh()->door_passcode);
        $this->assertSame('Cleaning', $room->fresh()->status);

        // Housekeeping finishes -> room back on sale.
        $task = HousekeepingTask::where('room_id', $room->id)->firstOrFail();
        $this->actingAs($staff)->ajax()->post(route('hms.housekeeping.finish', $task))->assertOk();
        $this->assertSame('Available', $room->fresh()->status);

        // Invoice downloads as PDF.
        $this->actingAs($staff)->get(route('hms.bookings.invoice', $booking))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_extend_and_move_room(): void
    {
        $staff = $this->user('admin');
        $this->addRoom('102');
        $booking = $this->walkIn($staff);
        $this->actingAs($staff)->ajax()->post(route('hms.bookings.check-in', $booking), ['room_id' => Room::where('room_number', '101')->value('id')])->assertOk();

        $this->actingAs($staff)->ajax()->post(route('hms.bookings.extend', $booking), [
            'check_out' => today()->addDays(2)->toDateString(),
        ])->assertOk();
        $booking->refresh();
        $this->assertTrue($booking->check_out_date->isSameDay(today()->addDays(2)));
        $this->assertSame(1, $booking->charges()->where('category', 'extend')->count());

        $target = Room::where('room_number', '102')->first();
        $this->actingAs($staff)->ajax()->post(route('hms.bookings.move', $booking), ['room_id' => $target->id, 'reason' => 'AC rusak'])->assertOk();

        $this->assertSame($target->id, $booking->reservation->fresh()->room_id);
        $this->assertSame('Occupied', $target->fresh()->status);
        $this->assertSame('Cleaning', Room::where('room_number', '101')->value('status'));
    }

    public function test_check_in_requires_a_free_room(): void
    {
        $staff = $this->user('admin');
        $booking = $this->walkIn($staff);
        Room::query()->update(['status' => 'Cleaning']);

        $this->actingAs($staff)->ajax()->post(route('hms.bookings.check-in', $booking), [
            'room_id' => Room::value('id'),
        ])->assertStatus(422);
    }

    public function test_walk_in_cannot_exceed_capacity(): void
    {
        $this->actingAs($this->user('admin'))->ajax()->post(route('hms.bookings.store'), [
            'room_type_id' => $this->type->id,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDay()->toDateString(),
            'guests' => 5,
            'guest_name' => 'Rombongan',
            'guest_phone' => '0812',
        ])->assertStatus(422);
    }
}
