<?php

namespace App\Models\Concerns;

use App\Models\Hotel;
use App\Support\HotelContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tenant isolation: while a hotel context is enforced (staff panel), every
 * query on the model only sees that hotel's rows, route-model binding
 * included, and new rows are stamped with the hotel.
 */
trait BelongsToHotel
{
    protected static function bootBelongsToHotel(): void
    {
        static::addGlobalScope('hotel', function (Builder $query) {
            if ($id = app(HotelContext::class)->scopeId()) {
                $query->where($query->getModel()->getTable() . '.hotel_id', $id);
            }
        });

        static::creating(function ($model) {
            if (empty($model->hotel_id) && method_exists($model, 'resolveHotelId')) {
                // Derive from the parent row (booking, room, room type, shift).
                $model->hotel_id = $model->resolveHotelId();
            }
            if (empty($model->hotel_id)) {
                $model->hotel_id = app(HotelContext::class)->id();
            }
        });
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
