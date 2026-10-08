<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

class MarkNoShows extends Command
{
    protected $signature = 'hms:mark-no-shows';

    protected $description = 'Mark paid/confirmed bookings whose check-in day has passed without arrival as no-show.';

    public function handle(BookingService $bookings): int
    {
        $count = 0;

        Booking::query()
            ->whereIn('status', ['Paid', 'Confirmed'])
            ->whereDate('check_in_date', '<', today())
            ->doesntHave('reservation')
            ->chunkById(100, function ($chunk) use ($bookings, &$count) {
                foreach ($chunk as $booking) {
                    $bookings->release($booking, 'NoShow', 'Tamu tidak datang pada tanggal check-in', 'no_show');
                    $count++;
                }
            });

        $this->info("{$count} booking ditandai no-show.");

        return self::SUCCESS;
    }
}
