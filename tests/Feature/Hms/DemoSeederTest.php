<?php

namespace Tests\Feature\Hms;

use App\Models\Booking;
use App\Models\Reservation;
use Carbon\CarbonPeriod;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Notification;

class DemoSeederTest extends HmsTestCase
{
    public function test_demo_data_is_consistent_and_rerunnable(): void
    {
        $this->seed(DemoSeeder::class);
        $first = Booking::where('booking_code', 'like', 'DMO-%')->count();
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThan(50, $first);
        $this->assertSame($first, Booking::where('booking_code', 'like', 'DMO-%')->count(), 'Re-running replaces the demo data.');

        $active = Reservation::where('status', 'Active')->with('room')->get();
        $this->assertGreaterThanOrEqual(1, $active->count());
        $this->assertTrue($active->every(fn ($r) => $r->room->status === 'Occupied' && preg_match('/^\d{6}$/', $r->door_passcode)));

        $nights = [];
        foreach (Reservation::with('booking')->get() as $r) {
            foreach (CarbonPeriod::create($r->booking->check_in_date, $r->booking->check_out_date->copy()->subDay()) as $n) {
                $key = $r->room_id . '|' . $n->toDateString();
                $this->assertArrayNotHasKey($key, $nights, 'A room must never be sold twice for one night.');
                $nights[$key] = true;
            }
        }

        Notification::assertNothingSent();

        $this->actingAs($this->superadminIn())->get(route('hms.reports.index'))->assertOk();
        $this->actingAs($this->user('Superadmin'))->get(route('platform.settlements.index'))->assertOk();
    }
}
