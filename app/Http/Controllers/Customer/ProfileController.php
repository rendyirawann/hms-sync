<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/** Guests manage their own account from the public site (the admin panel is staff-only). */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('frontend.customer.profile', ['user' => $request->user(), 'hotel' => Hotel::current()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:15'],
        ], [], ['name' => 'nama', 'phone' => 'nomor HP']);

        $request->user()->update($data);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [], ['current_password' => 'password lama', 'password' => 'password baru']);

        $request->user()->update(['password' => $request->password]);

        // Other sessions are signed out by AuthenticateSession on their next request.
        $request->session()->regenerate();

        return back()->with('success', 'Password berhasil diganti.');
    }
}
