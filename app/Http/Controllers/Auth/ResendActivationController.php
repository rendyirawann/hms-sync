<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResendActivationController extends Controller
{
    public function create(): View
    {
        return view('auth.resend-activation');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->string('email')->lower())->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        // Same answer either way so the form cannot be used to probe accounts.
        return redirect()->route('login')->with(
            'status',
            'Jika email tersebut terdaftar dan belum aktif, link aktivasi baru sudah dikirim.'
        );
    }
}
