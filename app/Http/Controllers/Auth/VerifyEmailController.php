<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Activate an account from the link in the activation email.
     *
     * The link is signed, so it is trusted without a session: a customer
     * cannot sign in before activating, which means they open this link while
     * logged out.
     */
    public function __invoke(string $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if (auth()->check()) {
            return redirect(auth()->user()->homeUrl());
        }

        return redirect()->route('login')->with('status', 'Akun Anda sudah aktif. Silakan masuk.');
    }
}
