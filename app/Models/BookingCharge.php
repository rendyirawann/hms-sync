<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingCharge extends Model
{
    public const CATEGORIES = [
        'minibar' => 'Minibar',
        'laundry' => 'Laundry',
        'restaurant' => 'Restoran',
        'late_checkout' => 'Late check-out',
        'extend' => 'Perpanjangan',
        'other' => 'Lainnya',
    ];

    protected $fillable = ['booking_id', 'category', 'description', 'quantity', 'unit_price', 'amount', 'created_by'];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
