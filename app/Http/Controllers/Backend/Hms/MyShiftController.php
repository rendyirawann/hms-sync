<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\EmployeeShift;
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

        return view('backend.hms.my_shift.index', compact('today', 'running', 'history'));
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

    private function authorizeOwner(Request $request, EmployeeShift $employeeShift): void
    {
        abort_unless($employeeShift->user_id === $request->user()->id, 403);
    }
}
