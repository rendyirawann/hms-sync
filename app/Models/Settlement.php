<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;

/**
 * Money the platform owes a hotel (or the hotel owes the platform) for the
 * stays completed in one period:
 *   payout = collected_by_platform - commission
 * Cash taken at the hotel's desk stays with the hotel, so a hotel that
 * collected mostly cash can end up with a negative payout (it owes commission).
 */
class Settlement extends Model
{
    use BelongsToHotel;

    protected $fillable = [
        'hotel_id', 'period_start', 'period_end', 'bookings_count', 'room_revenue', 'gross_amount',
        'collected_by_platform', 'collected_by_hotel', 'commission_amount', 'payout_amount',
        'status', 'paid_at', 'reference', 'note', 'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
        'room_revenue' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'collected_by_platform' => 'decimal:2',
        'collected_by_hotel' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'payout_amount' => 'decimal:2',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
