<?php

namespace Tests\Feature\Hms;

use App\Models\EmployeeShift;
use App\Models\Shift;

class ShiftAndAdminPagesTest extends HmsTestCase
{
    public function test_roster_clock_in_out_and_report(): void
    {
        $admin = $this->user('admin');
        $staff = $this->user('Pegawai');
        $shift = Shift::create(['name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00']);

        $this->actingAs($admin)->ajax()->post(route('hms.roster.store'), [
            'user_id' => $staff->id, 'shift_id' => $shift->id, 'date_from' => today()->toDateString(),
        ])->assertOk();
        $entry = EmployeeShift::firstOrFail();

        $this->actingAs($staff)->ajax()->post(route('hms.my-shift.start', $entry))->assertOk();
        $this->actingAs($staff)->ajax()->post(route('hms.my-shift.finish', $entry))->assertOk();
        $this->assertSame('Finished', $entry->fresh()->status);

        $this->actingAs($admin)->get(route('hms.reports.shifts'))->assertOk()->assertSee($staff->name);
        $csv = $this->actingAs($admin)->get(route('hms.reports.shifts', ['export' => 1]));
        $csv->assertOk();
        $this->assertStringContainsString($staff->name, $csv->streamedContent());
    }

    public function test_clock_in_respects_ip_and_geofence(): void
    {
        $staff = $this->user('Pegawai');
        $shift = Shift::create(['name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00']);
        $entry = EmployeeShift::create(['user_id' => $staff->id, 'shift_id' => $shift->id, 'date' => today(), 'status' => 'Pending']);

        $this->hotel->update(['clock_in_ips' => '10.0.0.0/8']);
        $this->actingAs($staff)->ajax()->post(route('hms.my-shift.start', $entry))->assertStatus(422);

        $this->hotel->update(['clock_in_ips' => null, 'latitude' => -6.2, 'longitude' => 106.8, 'clock_in_radius_m' => 100]);
        // ~11 km away.
        $this->actingAs($staff)->ajax()->post(route('hms.my-shift.start', $entry), ['latitude' => -6.3, 'longitude' => 106.8])->assertStatus(422);
        // On site.
        $this->actingAs($staff)->ajax()->post(route('hms.my-shift.start', $entry), ['latitude' => -6.2002, 'longitude' => 106.8])->assertOk();
    }

    public function test_every_admin_page_renders_for_superadmin(): void
    {
        $super = $this->user('Superadmin');

        foreach ([
            'dashboard', 'hms.hotel.edit', 'hms.room-types.index', 'hms.rooms.index', 'hms.staff.index', 'hms.shifts.index',
            'hms.pricing.index', 'hms.gallery.index', 'hms.reviews.index', 'hms.roster.index', 'hms.my-shift.index',
            'hms.bookings.index', 'hms.bookings.calendar', 'hms.housekeeping.index', 'hms.reports.index', 'hms.reports.shifts',
            'users.index', 'roles.index', 'settings.index', 'log-activity.index',
        ] as $route) {
            $this->actingAs($super)->get(route($route))->assertOk();
        }
    }

    public function test_master_data_crud_via_drawers(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->ajax()->post(route('hms.rate-rules.store'), [
            'name' => 'Lebaran', 'adjust_type' => 'fixed', 'value' => 900000, 'start_date' => '2027-03-01', 'end_date' => '2027-03-10', 'is_active' => 1,
        ])->assertOk();
        $this->actingAs($admin)->ajax()->post(route('hms.promo-codes.store'), [
            'code' => 'diskon50', 'type' => 'amount', 'value' => 50000, 'is_active' => 1,
        ])->assertOk();
        $this->assertDatabaseHas('promo_codes', ['code' => 'DISKON50']);

        $this->actingAs($admin)->ajax()->post(route('hms.promo-codes.store'), ['code' => 'bad code!', 'type' => 'amount', 'value' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $this->actingAs($admin)->ajax()->put(route('hms.hotel.update'), [
            'name' => 'Hotel Baru', 'address' => 'Jl. Baru', 'check_in_time' => '14:00', 'check_out_time' => '12:00',
            'tax_percent' => 11, 'service_percent' => 10, 'payment_deadline_hours' => 12, 'late_grace_minutes' => 5,
        ])->assertOk();
        $this->assertSame('Hotel Baru', $this->hotel->fresh()->name);
    }
}
