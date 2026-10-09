<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Booking extends Model
{
    use LogsActivity, BelongsToHotel;

    public const STATUSES = ['Pending', 'Paid', 'Confirmed', 'Cancelled', 'Completed', 'NoShow', 'Expired'];

    public const STATUS_LABELS = [
        'Pending' => 'Menunggu pembayaran',
        'Paid' => 'Sudah dibayar',
        'Confirmed' => 'Terkonfirmasi',
        'Completed' => 'Selesai',
        'Cancelled' => 'Dibatalkan',
        'NoShow' => 'Tidak datang',
        'Expired' => 'Kedaluwarsa',
    ];

    /** Bookings in these states still hold inventory. */
    public const HOLDING = ['Pending', 'Paid', 'Confirmed'];

    protected $fillable = [
        'hotel_id', 'user_id', 'room_type_id', 'check_in_date', 'check_out_date', 'total_price', 'booking_code', 'status',
        'guests', 'guest_phone', 'notes', 'cancel_reason', 'source', 'guest_name', 'guest_email',
        'room_total', 'discount', 'service_charge', 'tax', 'promo_code_id', 'payment_due_at', 'created_by',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'total_price' => 'decimal:2',
        'room_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'tax' => 'decimal:2',
        'guests' => 'integer',
        'payment_due_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('booking')
            ->logOnly(['status', 'check_in_date', 'check_out_date', 'total_price', 'cancel_reason', 'room_type_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => "Booking {$this->booking_code} {$event}");
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservation()
    {
        return $this->hasOne(Reservation::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function charges()
    {
        return $this->hasMany(BookingCharge::class);
    }

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    /** Bookings whose stay overlaps [checkIn, checkOut). */
    public function scopeOverlapping(Builder $query, $checkIn, $checkOut): Builder
    {
        return $query->where('check_in_date', '<', $checkOut)->where('check_out_date', '>', $checkIn);
    }

    public function getNightsAttribute(): int
    {
        return (int) $this->check_in_date->diffInDays($this->check_out_date);
    }

    /** Account holder for online bookings, the typed-in name for walk-ins. */
    public function getGuestDisplayNameAttribute(): string
    {
        return $this->user?->name ?? $this->guest_name ?? 'Tamu';
    }

    public function getGuestContactEmailAttribute(): ?string
    {
        return $this->user?->email ?? $this->guest_email;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'Pending' => 'warning',
            'Paid' => 'info',
            'Confirmed' => 'primary',
            'Completed' => 'success',
            'NoShow', 'Expired' => 'secondary',
            default => 'danger',
        };
    }

    public function getIsCheckedInAttribute(): bool
    {
        return $this->reservation?->status === 'Active';
    }

    /** Room + extra charges (minibar, laundry, extensions ...). */
    public function getGrandTotalAttribute(): float
    {
        return (float) $this->total_price + (float) $this->charges->sum('amount');
    }

    public function getAmountPaidAttribute(): float
    {
        return (float) $this->payments->where('status', 'paid')->sum('amount');
    }

    public function getBalanceDueAttribute(): float
    {
        return round(max(0, $this->grand_total - $this->amount_paid), 2);
    }

    /** hotel_id follows the parent roomtype. */
    protected function resolveHotelId(): ?int
    {
        return $this->room_type_id ? RoomType::withoutGlobalScopes()->whereKey($this->room_type_id)->value('hotel_id') : null;
    }
}
