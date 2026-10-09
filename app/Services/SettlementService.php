<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Settlement;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Settles completed stays of one hotel for a period:
 *   commission = Σ (room_total - discount) × booking commission %
 *   collected_by_platform = online payments (Tripay, verified transfer)
 *   collected_by_hotel    = cash / EDC taken at the hotel's desk
 *   payout = collected_by_platform - commission   (negative: hotel owes)
 * Each booking is settled once (bookings.settlement_id).
 */
class SettlementService
{
    public const PLATFORM_METHODS = ['tripay', 'transfer'];

    /** Preview without saving: the numbers a settlement would have. */
    public function preview(Hotel $hotel, CarbonInterface $from, CarbonInterface $to): array
    {
        $bookings = $this->eligible($hotel, $from, $to)->get();

        $totals = [
            'bookings_count' => $bookings->count(),
            'room_revenue' => 0.0,
            'gross_amount' => 0.0,
            'collected_by_platform' => 0.0,
            'collected_by_hotel' => 0.0,
            'commission_amount' => 0.0,
        ];

        foreach ($bookings as $booking) {
            $roomRevenue = (float) $booking->room_total - (float) $booking->discount;
            $percent = $booking->commission_percent ?? $hotel->commission_percent;

            $totals['room_revenue'] += $roomRevenue;
            $totals['gross_amount'] += (float) $booking->total_price + (float) $booking->charges->sum('amount');
            $totals['commission_amount'] += round($roomRevenue * (float) $percent / 100, 2);

            foreach ($booking->payments->where('status', 'paid') as $payment) {
                $key = in_array($payment->method, self::PLATFORM_METHODS, true) ? 'collected_by_platform' : 'collected_by_hotel';
                $totals[$key] += (float) $payment->amount;
            }
        }

        $totals = array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $totals);
        $totals['payout_amount'] = round($totals['collected_by_platform'] - $totals['commission_amount'], 2);

        return $totals;
    }

    public function create(Hotel $hotel, CarbonInterface $from, CarbonInterface $to, ?string $by = null): ?Settlement
    {
        return DB::transaction(function () use ($hotel, $from, $to, $by) {
            $ids = $this->eligible($hotel, $from, $to)->lockForUpdate()->pluck('id');

            if ($ids->isEmpty()) {
                return null;
            }

            $settlement = Settlement::create($this->preview($hotel, $from, $to) + [
                'hotel_id' => $hotel->id,
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
                'status' => 'pending',
                'created_by' => $by,
            ]);

            Booking::withoutGlobalScopes()->whereIn('id', $ids)->update(['settlement_id' => $settlement->id]);

            return $settlement;
        });
    }

    private function eligible(Hotel $hotel, CarbonInterface $from, CarbonInterface $to)
    {
        return Booking::withoutGlobalScopes()
            ->with(['payments' => fn ($q) => $q->withoutGlobalScopes(), 'charges'])
            ->where('hotel_id', $hotel->id)
            ->where('status', 'Completed')
            ->whereNull('settlement_id')
            ->whereBetween('check_out_date', [$from->toDateString(), $to->toDateString()]);
    }
}
