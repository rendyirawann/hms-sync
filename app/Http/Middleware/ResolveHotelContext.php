<?php

namespace App\Http\Middleware;

use App\Models\Hotel;
use App\Support\HotelContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel: pins the request to the user's hotel. Staff can never leave
 * their hotel; a Superadmin works platform-wide unless a hotel is picked.
 */
class ResolveHotelContext
{
    public const SESSION_KEY = 'hms.hotel_id';

    public function handle(Request $request, Closure $next, string $mode = 'optional'): Response
    {
        $user = $request->user();
        $context = app(HotelContext::class);

        if ($user && $user->hasRole('Superadmin')) {
            $hotel = ($id = $request->session()->get(self::SESSION_KEY)) ? Hotel::find($id) : null;
            // Platform pages always see every hotel, even while one is picked.
            $context->set($hotel, enforce: ! $request->routeIs('platform.*'));

            if ($mode === 'required' && ! $hotel) {
                return redirect()->route('platform.hotels.index')
                    ->with('warning', 'Pilih hotel yang ingin dikelola terlebih dahulu (tombol "Kelola").');
            }
        } elseif ($user && $user->hotel_id) {
            // Fresh lookup: never trust a relation cached on the user object.
            $context->set(Hotel::find($user->hotel_id));
        } elseif ($mode === 'required') {
            abort(403, 'Akun ini belum terhubung ke hotel mana pun.');
        }

        return $next($request);
    }
}
