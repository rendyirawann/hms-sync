<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Guest (customer) self-registration can be switched off in Settings;
     * both the form and the handler 404 while it is.
     */
    private function guardRegistrationEnabled(): void
    {
        abort_unless(Setting::get('allow_registration', '0') === '1', 404);
    }

    public function create(): View
    {
        $this->guardRegistrationEnabled();

        return view('auth.register');
    }

    /**
     * Create a customer account and email the activation link. The customer
     * is NOT signed in: login is refused until the link has been opened.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $this->guardRegistrationEnabled();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // 'password' is hashed by the model cast; passing the plain value here
        // avoids double hashing.
        $user = User::create($validated + ['is_active' => true]);
        $user->assignRole('Customer');

        // Laravel's listener sends the activation email for MustVerifyEmail users.
        event(new Registered($user));

        return redirect()->route('login')->with(
            'status',
            'Pendaftaran berhasil. Kami sudah mengirim link aktivasi ke ' . $user->email . '. Buka email tersebut untuk mengaktifkan akun.'
        );
    }
}
