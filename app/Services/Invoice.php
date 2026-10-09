<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Hotel;
use Barryvdh\DomPDF\Facade\Pdf;

class Invoice
{
    public function download(Booking $booking)
    {
        $booking->load(['user', 'roomType', 'reservation.room', 'charges', 'payments', 'promoCode', 'hotel']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'booking' => $booking,
            'hotel' => $booking->hotel,
        ])->setPaper('a4');

        return $pdf->download('Invoice-' . $booking->booking_code . '.pdf');
    }
}
