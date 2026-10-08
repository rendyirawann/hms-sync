<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tripay payment gateway
    |--------------------------------------------------------------------------
    | https://tripay.co.id/developer — use the sandbox credentials until the
    | merchant is approved. The callback URL to register in the Tripay
    | dashboard is {APP_URL}/payment/tripay/callback.
    */
    'tripay' => [
        'enabled' => (bool) env('TRIPAY_API_KEY'),
        'mode' => env('TRIPAY_MODE', 'sandbox'), // sandbox | production
        'api_key' => env('TRIPAY_API_KEY'),
        'private_key' => env('TRIPAY_PRIVATE_KEY'),
        'merchant_code' => env('TRIPAY_MERCHANT_CODE'),
        // Minutes a Tripay invoice stays payable (capped by the booking deadline).
        'expiry_minutes' => (int) env('TRIPAY_EXPIRY_MINUTES', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Smart door lock
    |--------------------------------------------------------------------------
    | "log" only records the code (no hardware). Add a driver implementing
    | App\Services\DoorLock\DoorLock for TTLock, Nuki, etc.
    */
    'door_lock' => [
        'driver' => env('DOOR_LOCK_DRIVER', 'log'), // log | ttlock
    ],

    // TTLock Open Platform (https://euopen.ttlock.com). The account is the
    // TTLock app account that owns the locks; each room stores its lockId.
    'ttlock' => [
        'base_url' => env('TTLOCK_BASE_URL', 'https://euapi.ttlock.com'),
        'client_id' => env('TTLOCK_CLIENT_ID'),
        'client_secret' => env('TTLOCK_CLIENT_SECRET'),
        'username' => env('TTLOCK_USERNAME'),
        'password' => env('TTLOCK_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Realtime (Reverb) — what the BROWSER connects to
    |--------------------------------------------------------------------------
    | REVERB_HOST/PORT/SCHEME are what PHP uses to publish events (often an
    | internal address such as 127.0.0.1:8080 or a container name). Browsers
    | need the public address behind the proxy, e.g. hotel.example.com:443.
    */
    'reverb_client' => [
        'host' => env('VITE_REVERB_HOST'),
        'port' => env('VITE_REVERB_PORT'),
        'scheme' => env('VITE_REVERB_SCHEME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Housekeeping / no-show
    |--------------------------------------------------------------------------
    */
    // A confirmed guest who has not arrived by this time on the day after the
    // check-in date is marked no-show and the room is released.
    'no_show_cutoff' => env('HMS_NO_SHOW_CUTOFF', '02:00'),
];
