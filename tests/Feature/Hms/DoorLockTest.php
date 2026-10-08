<?php

namespace Tests\Feature\Hms;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DoorLockTest extends HmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hms.door_lock.driver' => 'ttlock',
            'hms.ttlock.base_url' => 'https://ttlock.test',
            'hms.ttlock.client_id' => 'cid',
            'hms.ttlock.client_secret' => 'secret',
            'hms.ttlock.username' => 'hotel',
            'hms.ttlock.password' => 'pw',
        ]);
        Cache::forget('ttlock.token');
        Room::query()->update(['lock_id' => '7788']);
    }

    private function checkedInBooking(): Booking
    {
        $staff = $this->user('admin');
        $this->actingAs($staff)->ajax()->post(route('hms.bookings.store'), [
            'room_type_id' => $this->type->id,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDay()->toDateString(),
            'guests' => 1, 'guest_name' => 'Tamu Kunci', 'guest_phone' => '0812',
        ])->assertOk();
        $booking = Booking::latest('id')->first();
        $this->actingAs($staff)->ajax()->post(route('hms.bookings.paid', $booking), ['method' => 'cash'])->assertOk();

        return $booking;
    }

    public function test_check_in_programs_and_check_out_removes_the_code(): void
    {
        Http::fake([
            'ttlock.test/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 7776000]),
            'ttlock.test/v3/keyboardPwd/add' => Http::response(['keyboardPwdId' => 555]),
            'ttlock.test/v3/keyboardPwd/delete' => Http::response(['errcode' => 0]),
        ]);

        $booking = $this->checkedInBooking();
        $staff = $this->user('admin');
        $response = $this->actingAs($staff)->ajax()->post(route('hms.bookings.check-in', $booking), ['room_id' => Room::value('id')]);
        $response->assertOk();
        $this->assertStringNotContainsString('belum tersinkron', $response->json('message'));

        $code = $booking->fresh()->reservation->door_passcode;
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v3/keyboardPwd/add')
            && $r['lockId'] === '7788' && $r['keyboardPwd'] === $code && $r['addType'] === 2 && $r['accessToken'] === 'tok');
        // Password is sent as MD5, never in clear text.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/oauth2/token') && $r['password'] === md5('pw'));
        $this->assertSame('555', Room::first()->lock_code_ref);

        $this->actingAs($staff)->ajax()->post(route('hms.bookings.check-out', $booking))->assertOk();
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v3/keyboardPwd/delete') && $r['keyboardPwdId'] === '555');
        $this->assertNull(Room::first()->lock_code_ref);
    }

    public function test_offline_lock_does_not_block_check_in(): void
    {
        Http::fake([
            'ttlock.test/oauth2/token' => Http::response(['access_token' => 'tok']),
            'ttlock.test/v3/keyboardPwd/add' => Http::response(['errcode' => -2012, 'errmsg' => 'Gateway offline']),
        ]);

        $booking = $this->checkedInBooking();
        $response = $this->actingAs($this->user('admin'))->ajax()->post(route('hms.bookings.check-in', $booking), ['room_id' => Room::value('id')]);

        $response->assertOk();
        $this->assertStringContainsString('belum tersinkron', $response->json('message'));
        $this->assertTrue($booking->fresh()->is_checked_in);
    }

    public function test_expired_token_is_refreshed_once(): void
    {
        Http::fake([
            'ttlock.test/oauth2/token' => Http::sequence()->push(['access_token' => 'old'])->push(['access_token' => 'new']),
            'ttlock.test/v3/keyboardPwd/add' => Http::sequence()
                ->push(['errcode' => 10004, 'errmsg' => 'invalid grant'])
                ->push(['keyboardPwdId' => 9]),
        ]);

        $booking = $this->checkedInBooking();
        $response = $this->actingAs($this->user('admin'))->ajax()->post(route('hms.bookings.check-in', $booking), ['room_id' => Room::value('id')]);

        $response->assertOk();
        $this->assertStringNotContainsString('belum tersinkron', $response->json('message'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v3/keyboardPwd/add') && $r['accessToken'] === 'new');
    }
}
