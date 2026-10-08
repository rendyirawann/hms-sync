<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\EmployeeShift;
use App\Models\Hotel;
use Symfony\Component\HttpFoundation\IpUtils;
use Illuminate\Http\Request;

/** Clock-in / clock-out for the signed-in employee. */
class MyShiftController extends Controller
{
    use RespondsWithAlert;

    public function index(Request $request)
    {
        $user = $request->user();

        $today = $user->employeeShifts()->with('shift')->whereDate('date', today())->first();
        // A night shift started yesterday may still be running.
        $running = $user->employeeShifts()->with('shift')->where('status', 'Started')->latest('start_at')->first();
        $history = $user->employeeShifts()->with('shift')
            ->whereBetween('date', [today()->subDays(30), today()->addDays(14)])
            ->orderByDesc('date')
            ->get();

        $hotel = Hotel::current();
        $needsLocation = $hotel->clock_in_radius_m && $hotel->latitude !== null && $hotel->longitude !== null;

        return view('backend.hms.my_shift.index', compact('today', 'running', 'history', 'needsLocation'));
    }

    public function start(Request $request, EmployeeShift $employeeShift)
    {
        $this->authorizeOwner($request, $employeeShift);

        if ($employeeShift->status !== 'Pending' || ! $employeeShift->date->isToday()) {
            return $this->fail($request, 'Shift ini tidak bisa dimulai sekarang.');
        }

        if ($request->user()->employeeShifts()->where('status', 'Started')->exists()) {
            return $this->fail($request, 'Masih ada shift yang berjalan. Akhiri dulu shift tersebut.');
        }

        if ($reason = $this->clockInBlocked($request)) {
            return $this->fail($request, $reason);
        }

        $employeeShift->update(['status' => 'Started', 'start_at' => now()]);

        return $this->done($request, 'Shift dimulai pukul ' . now()->format('H:i') . '. Selamat bekerja!');
    }

    public function finish(Request $request, EmployeeShift $employeeShift)
    {
        $this->authorizeOwner($request, $employeeShift);

        if ($employeeShift->status !== 'Started') {
            return $this->fail($request, 'Shift ini belum dimulai atau sudah selesai.');
        }

        $employeeShift->update(['status' => 'Finished', 'end_at' => now()]);

        return $this->done($request, 'Shift selesai pukul ' . now()->format('H:i') . '. Terima kasih!');
    }

    /** Optional guards configured on the hotel profile: office IPs and/or a GPS radius. */
    private function clockInBlocked(Request $request): ?string
    {
        $hotel = Hotel::current();

        $ips = array_filter(array_map('trim', explode(',', (string) $hotel->clock_in_ips)));
        if ($ips && ! IpUtils::checkIp((string) $request->ip(), $ips)) {
            return 'Clock-in hanya bisa dari jaringan hotel (IP Anda: ' . $request->ip() . ').';
        }

        if ($hotel->clock_in_radius_m && $hotel->latitude !== null && $hotel->longitude !== null) {
            $lat = $request->input('latitude');
            $lng = $request->input('longitude');

            if (! is_numeric($lat) || ! is_numeric($lng)) {
                return 'Izinkan akses lokasi di browser untuk clock-in.';
            }

            $distance = $this->distanceMeters((float) $lat, (float) $lng, (float) $hotel->latitude, (float) $hotel->longitude);
            if ($distance > $hotel->clock_in_radius_m) {
                return 'Anda berada ' . number_format($distance, 0, ',', '.') . ' m dari hotel. Clock-in maksimal ' . $hotel->clock_in_radius_m . ' m.';
            }
        }

        return null;
    }

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function authorizeOwner(Request $request, EmployeeShift $employeeShift): void
    {
        abort_unless($employeeShift->user_id === $request->user()->id, 403);
    }
}
