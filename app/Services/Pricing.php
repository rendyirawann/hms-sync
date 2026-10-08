<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\PromoCode;
use App\Models\RateRule;
use App\Models\RoomType;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Prices a stay night by night (rate rules), then applies promo, service
 * charge and tax:  total = (room_total - discount) + service + tax, where
 * service is on the discounted room total and tax on (discounted + service).
 */
class Pricing
{
    private ?Collection $rules = null;

    /**
     * @return array{nights: array<string, float>, room_total: float, discount: float,
     *               service_charge: float, tax: float, total: float, promo: ?PromoCode}
     */
    public function quote(RoomType $type, CarbonInterface $checkIn, CarbonInterface $checkOut, ?PromoCode $promo = null): array
    {
        $hotel = Hotel::current();
        $nights = [];

        foreach (CarbonPeriod::create($checkIn, $checkOut->copy()->subDay()) as $night) {
            $nights[$night->toDateString()] = $this->nightly($type, $night);
        }

        $roomTotal = round(array_sum($nights), 2);
        $discount = $promo ? $promo->discountFor($roomTotal) : 0.0;
        $net = $roomTotal - $discount;
        $service = round($net * (float) $hotel->service_percent / 100, 2);
        $tax = round(($net + $service) * (float) $hotel->tax_percent / 100, 2);

        return [
            'nights' => $nights,
            'room_total' => $roomTotal,
            'discount' => $discount,
            'service_charge' => $service,
            'tax' => $tax,
            'total' => round($net + $service + $tax, 2),
            'promo' => $promo,
        ];
    }

    /** Price for one night after the winning rate rule. */
    public function nightly(RoomType $type, CarbonInterface $night): float
    {
        $base = (float) $type->base_price;

        $rule = $this->rules()->first(fn (RateRule $rule) => $rule->appliesTo($type->id, $night));

        return round($rule ? $rule->apply($base) : $base, 2);
    }

    /** Lowest nightly price over the next 30 days, for "mulai dari" labels. */
    public function startingFrom(RoomType $type): float
    {
        $prices = [];
        foreach (CarbonPeriod::create(today(), today()->addDays(29)) as $night) {
            $prices[] = $this->nightly($type, $night);
        }

        return min($prices);
    }

    private function rules(): Collection
    {
        return $this->rules ??= RateRule::where('is_active', true)
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();
    }
}
