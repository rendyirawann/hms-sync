<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\HotelStatusNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/**
 * Hotel owners sign their property up. The owner gets an admin account for
 * that hotel (email activation required) and can prepare rooms while the
 * platform reviews; the hotel is hidden from guests until approved.
 */
class PartnerRegistrationController extends Controller
{
    public function create()
    {
        return view('auth.partner-register');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'hotel_name' => ['required', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'hotel_phone' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'agree' => ['accepted'],
        ], [
            'agree.accepted' => 'Anda harus menyetujui ketentuan mitra.',
        ], [
            'hotel_name' => 'nama hotel', 'city' => 'kota', 'address' => 'alamat hotel', 'hotel_phone' => 'telepon hotel',
            'name' => 'nama pemilik',
        ]);

        $autoApprove = Setting::get('platform_auto_approve_hotels', '0') === '1';

        [$hotel, $owner] = DB::transaction(function () use ($data, $autoApprove) {
            $hotel = Hotel::create([
                'name' => $data['hotel_name'],
                'city' => $data['city'],
                'address' => $data['address'],
                'phone' => $data['hotel_phone'],
                'email' => $data['email'],
                'commission_percent' => (float) Setting::get('platform_default_commission', '10'),
                'approval_status' => $autoApprove ? 'approved' : 'pending',
                'approved_at' => $autoApprove ? now() : null,
                'status' => $autoApprove,
            ]);

            $owner = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'hotel_id' => $hotel->id,
                'is_active' => true,
            ]);
            $owner->assignRole('admin');
            $hotel->update(['owner_id' => $owner->id]);

            return [$hotel, $owner];
        });

        event(new Registered($owner));   // activation email
        $owner->notify(new HotelStatusNotification($hotel, $autoApprove ? 'approved' : 'registered'));

        return redirect()->route('login')->with(
            'status',
            'Pendaftaran ' . $hotel->name . ' diterima. Aktifkan akun lewat email yang kami kirim ke ' . $owner->email
                . ($autoApprove ? ', lalu hotel Anda langsung bisa dikelola.' : '. Hotel tampil di website setelah disetujui tim kami.')
        );
    }
}
