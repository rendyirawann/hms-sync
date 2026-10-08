<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler — needs one cron entry on the server:
|   * * * * * cd /path/to/hms-sync && php artisan schedule:run >> /dev/null 2>&1
|--------------------------------------------------------------------------
*/

// Release rooms held by online bookings that were never paid.
Schedule::command('hms:expire-bookings')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Guests who never arrived: release the room after the night has passed.
Schedule::command('hms:mark-no-shows')->dailyAt(config('hms.no_show_cutoff', '02:00'))->withoutOverlapping()->onOneServer();

// Database + uploaded files backup (spatie/laravel-backup, config/backup.php).
Schedule::command('backup:clean')->dailyAt('01:00')->onOneServer();
Schedule::command('backup:run')->dailyAt('01:30')->onOneServer();
Schedule::command('backup:monitor')->dailyAt('03:00')->onOneServer();
