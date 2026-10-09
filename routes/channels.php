<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Sidebar "Who's Online" (presence channel) — returns the member payload.
Broadcast::channel('online-users', function ($user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'avatar_url' => $user->avatar ? asset('storage/user/avatar/' . $user->avatar) : null,
        'initials' => substr($user->name, 0, 1),
    ];
});

// Personal notifications / force logout / a guest's own booking updates.
// User ids are UUIDs: compare as strings. Casting to int would turn
// "019e0b1d-..." into 19 and let users subscribe to each other's channel.
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return hash_equals((string) $user->id, (string) $id);
});

// A hotel's staff only ever hear their own hotel; the platform hears all.
$worksAt = fn ($user, $hotelId) => $user->hasRole('Superadmin') || (int) $user->hotel_id === (int) $hotelId;

// Front desk: new bookings, payments, check-in/out (staff with booking access).
Broadcast::channel('hms.hotel.{hotelId}.frontdesk', fn ($user, $hotelId) => $worksAt($user, $hotelId) && $user->can('hms.booking'));

// Housekeeping board and room status.
Broadcast::channel('hms.hotel.{hotelId}.housekeeping', fn ($user, $hotelId) => $worksAt($user, $hotelId)
    && ($user->can('hms.housekeeping') || $user->can('hms.booking')));
