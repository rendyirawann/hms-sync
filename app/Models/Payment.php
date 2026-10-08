<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'booking_id', 'method', 'channel', 'amount', 'status', 'reference', 'merchant_ref', 'checkout_url',
        'proof_path', 'payload', 'paid_at', 'expires_at', 'verified_by', 'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['payload'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getMethodLabelAttribute(): string
    {
        return match ($this->method) {
            'transfer' => 'Transfer manual',
            'tripay' => 'Tripay' . ($this->channel ? ' (' . $this->channel . ')' : ''),
            'cash' => 'Tunai / EDC',
            default => ucfirst((string) $this->method),
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            'pending' => 'Menunggu',
            'paid' => 'Lunas',
            'rejected' => 'Ditolak',
            'expired' => 'Kedaluwarsa',
            'failed' => 'Gagal',
        ][$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'success',
            'pending' => 'warning',
            default => 'danger',
        };
    }
}
