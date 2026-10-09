<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Nightly price adjustment. The highest-priority active rule matching a
 * night wins: percent (+/- % of base), amount (+/- Rp) or fixed (exact Rp).
 */
class RateRule extends Model
{
    use BelongsToHotel;

    public const TYPES = ['percent' => 'Persen (%)', 'amount' => 'Tambah/kurang (Rp)', 'fixed' => 'Harga tetap (Rp)'];

    protected $fillable = [
        'hotel_id', 'room_type_id', 'name', 'start_date', 'end_date', 'days_of_week', 'adjust_type', 'value', 'priority', 'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days_of_week' => 'array',
        'value' => 'decimal:2',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function appliesTo(int $roomTypeId, CarbonInterface $night): bool
    {
        return $this->is_active
            && ($this->room_type_id === null || (int) $this->room_type_id === $roomTypeId)
            && ($this->start_date === null || $night->gte($this->start_date))
            && ($this->end_date === null || $night->lte($this->end_date))
            && (empty($this->days_of_week) || in_array($night->dayOfWeek, array_map('intval', $this->days_of_week), true));
    }

    public function apply(float $base): float
    {
        $value = (float) $this->value;

        return max(0, match ($this->adjust_type) {
            'percent' => $base + $base * $value / 100,
            'amount' => $base + $value,
            default => $value, // fixed
        });
    }
}
