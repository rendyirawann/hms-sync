<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\EmployeeShift;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** Occupancy, ADR, RevPAR and revenue for one month. */
    public function index(Request $request)
    {
        [$from, $to] = $this->month($request);
        $days = $from->diffInDays($to) + 1;
        $rooms = Room::where('status', '!=', 'Maintenance')->count();
        $capacity = max(1, $rooms * $days);

        $bookings = Booking::query()
            ->where(fn ($q) => $q->whereIn('status', ['Paid', 'Confirmed', 'Completed']))
            ->overlapping($from->toDateString(), $to->copy()->addDay()->toDateString())
            ->with('roomType:id,name')
            ->get(['id', 'room_type_id', 'check_in_date', 'check_out_date', 'room_total', 'discount', 'status']);

        $soldNights = 0;
        $roomRevenue = 0.0;
        $perDay = array_fill_keys(collect(CarbonPeriod::create($from, $to))->map->toDateString()->all(), 0);
        $byType = [];

        foreach ($bookings as $booking) {
            $nightly = $booking->nights > 0 ? ((float) $booking->room_total - (float) $booking->discount) / $booking->nights : 0;

            foreach (CarbonPeriod::create($booking->check_in_date, $booking->check_out_date->copy()->subDay()) as $night) {
                if ($night->lt($from) || $night->gt($to)) {
                    continue;
                }
                $soldNights++;
                $roomRevenue += $nightly;
                $perDay[$night->toDateString()]++;
                $name = $booking->roomType->name ?? '-';
                $byType[$name]['nights'] = ($byType[$name]['nights'] ?? 0) + 1;
                $byType[$name]['revenue'] = ($byType[$name]['revenue'] ?? 0) + $nightly;
            }
        }

        $collected = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(paid_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $today = [
            'arrivals' => Booking::whereIn('status', ['Paid', 'Confirmed'])->whereDate('check_in_date', today())->doesntHave('reservation')->count(),
            'departures' => Reservation::where('status', 'Active')->whereHas('booking', fn ($b) => $b->whereDate('check_out_date', '<=', today()))->count(),
            'in_house' => Reservation::where('status', 'Active')->count(),
            'rooms' => $rooms,
        ];
        $today['occupancy'] = $rooms ? round($today['in_house'] / $rooms * 100, 1) : 0;

        return view('backend.hms.reports.index', [
            'from' => $from,
            'to' => $to,
            'today' => $today,
            'metrics' => [
                'occupancy' => round($soldNights / $capacity * 100, 1),
                'adr' => $soldNights ? $roomRevenue / $soldNights : 0,
                'revpar' => $roomRevenue / $capacity,
                'room_revenue' => $roomRevenue,
                'collected' => (float) $collected->sum(),
                'sold_nights' => $soldNights,
                'bookings' => Booking::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
                'cancelled' => Booking::whereIn('status', ['Cancelled', 'Expired', 'NoShow'])->whereBetween('updated_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
            ],
            'chart' => [
                'labels' => array_map(fn ($d) => Carbon::parse($d)->format('d'), array_keys($perDay)),
                'occupancy' => array_map(fn ($n) => $rooms ? round($n / $rooms * 100, 1) : 0, array_values($perDay)),
                'revenue' => array_map(fn ($d) => (float) ($collected[$d] ?? 0), array_keys($perDay)),
            ],
            'byType' => collect($byType)->sortByDesc('revenue'),
        ]);
    }

    /** Monthly attendance per employee: shifts, hours, late arrivals. */
    public function shifts(Request $request)
    {
        [$from, $to] = $this->month($request);
        $rows = $this->shiftRows($from, $to);

        if ($request->boolean('export')) {
            return $this->exportShifts($rows, $from);
        }

        return view('backend.hms.reports.shifts', compact('from', 'to', 'rows'));
    }

    private function shiftRows(Carbon $from, Carbon $to)
    {
        $grace = Hotel::current()->late_grace_minutes;

        return EmployeeShift::with(['user:id,name', 'shift:id,name,start_time,end_time'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy('user_id')
            ->map(function ($entries) use ($grace) {
                $worked = $entries->whereNotNull('start_at');
                $late = $worked->filter(function ($e) use ($grace) {
                    $scheduled = $e->date->copy()->setTimeFromTimeString($e->shift->start_time)->addMinutes($grace);

                    return $e->start_at->gt($scheduled);
                });

                return [
                    'name' => $entries->first()->user->name ?? '-',
                    'scheduled' => $entries->count(),
                    'worked' => $worked->count(),
                    'absent' => $entries->filter(fn ($e) => $e->status === 'Pending' && $e->date->lt(today()))->count(),
                    'late' => $late->count(),
                    'hours' => round($worked->sum(fn ($e) => $e->end_at ? $e->start_at->diffInMinutes($e->end_at) : 0) / 60, 1),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    /** CSV with BOM + semicolon separator so Excel (ID locale) opens it directly. */
    private function exportShifts($rows, Carbon $from): StreamedResponse
    {
        $filename = 'rekap-shift-' . $from->format('Y-m') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Pegawai', 'Jadwal', 'Hadir', 'Tidak hadir', 'Terlambat', 'Total jam'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [$row['name'], $row['scheduled'], $row['worked'], $row['absent'], $row['late'], str_replace('.', ',', (string) $row['hours'])], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function month(Request $request): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month')) ? $request->input('month') : today()->format('Y-m');
        $from = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();

        return [$from, $from->copy()->endOfMonth()->startOfDay()];
    }
}
