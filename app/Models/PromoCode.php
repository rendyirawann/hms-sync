<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    use BelongsToHotel;

    protected $fillable = [
        'hotel_id', 'code', 'type', 'value', 'max_discount', 'min_nights', 'valid_from', 'valid_until', 'max_uses', 'used_count', 'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'min_nights' => 'integer',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    /** Why the code cannot be used for this stay, or null when it can. */
    public function rejectionReason(int $nights, CarbonInterface $checkIn): ?string
    {
        return match (true) {
            ! $this->is_active => 'Kode promo tidak aktif.',
            $this->valid_from && $checkIn->lt($this->valid_from) => 'Kode promo belum berlaku untuk tanggal ini.',
            $this->valid_until && $checkIn->gt($this->valid_until) => 'Kode promo sudah kedaluwarsa.',
            $this->max_uses !== null && $this->used_count >= $this->max_uses => 'Kuota kode promo sudah habis.',
            $nights < $this->min_nights => "Kode promo minimal {$this->min_nights} malam.",
            default => null,
        };
    }

    public function discountFor(float $roomTotal): float
    {
        $discount = $this->type === 'percent' ? $roomTotal * (float) $this->value / 100 : (float) $this->value;

        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return round(min($discount, $roomTotal), 2);
    }
}
