<?php

namespace Tests\Feature\Hms;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

class AuthFlowTest extends HmsTestCase
{
    public function test_customer_registers_activates_and_logs_in(): void
    {
        $this->post('/admin/register', [
            'name' => 'Tamu Uji',
            'email' => 'tamu@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('login'));

        $user = User::where('email', 'tamu@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('Customer'));
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertNotEmpty($user->username);
        $this->assertGuest();
        Notification::assertSentTo($user, VerifyEmail::class);

        // Not activated yet: login is refused.
        $this->post('/admin/login', ['email' => 'tamu@example.test', 'password' => 'Password123!'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Activation link works while logged out.
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($link)->assertRedirect(route('login'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->post('/admin/login', ['email' => 'tamu@example.test', 'password' => 'Password123!'])
            ->assertRedirect(route('customer.bookings.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_tampered_activation_link_is_rejected(): void
    {
        $user = $this->user('Customer', ['email_verified_at' => null]);
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('other@example.test')]);

        $this->get($link)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_customer_cannot_open_admin_panel_and_staff_can(): void
    {
        $this->actingAs($this->user('Customer'))->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($this->user('Pegawai'))->get('/admin/dashboard')->assertOk();
    }

    public function test_pegawai_permissions_are_limited(): void
    {
        $pegawai = $this->user('Pegawai');

        $this->actingAs($pegawai)->get(route('hms.bookings.index'))->assertOk();
        $this->actingAs($pegawai)->get(route('hms.housekeeping.index'))->assertOk();
        $this->actingAs($pegawai)->get(route('hms.rooms.index'))->assertForbidden();
        $this->actingAs($pegawai)->get(route('hms.reports.index'))->assertForbidden();
        $this->actingAs($pegawai)->get(route('settings.index'))->assertForbidden();
    }

    public function test_customer_updates_profile_and_password(): void
    {
        $user = $this->user('Customer');

        $this->actingAs($user)->put(route('customer.profile.update'), ['name' => 'Nama Baru', 'phone' => '0812'])->assertSessionHasNoErrors();
        $this->assertSame('Nama Baru', $user->fresh()->name);

        $this->actingAs($user)->put(route('customer.profile.password'), [
            'current_password' => 'salah',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertSessionHasErrors('current_password');
    }
}
