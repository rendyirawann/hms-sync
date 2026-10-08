<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

class ExpireUnpaidBookings extends Command
{
    protected $signature = 'hms:expire-bookings';

    protected $description = 'Cancel online bookings whose payment deadline has passed, releasing the room.';

    public function handle(BookingService $bookings): int
    {
        $count = 0;

        Booking::query()
            ->where('status', 'Pending')
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<', now())
            // A transfer receipt waiting for verification keeps the booking alive.
            ->whereDoesntHave('payments', fn ($q) => $q->where('method', 'transfer')->where('status', 'pending'))
            ->chunkById(100, function ($chunk) use ($bookings, &$count) {
                foreach ($chunk as $booking) {
                    $bookings->release($booking, 'Expired', 'Batas waktu pembayaran terlewati', 'expired');
                    $count++;
                }
            });

        $this->info("{$count} booking kedaluwarsa.");

        return self::SUCCESS;
    }
}
