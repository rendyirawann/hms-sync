<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Weekly shift schedule for hotel staff. */
class RosterController extends Controller
{
    use RespondsWithAlert;

    public function index(Request $request)
    {
        $start = Carbon::parse($request->input('week', today()->toDateString()))->startOfWeek();
        $days = CarbonPeriod::create($start, $start->copy()->addDays(6));

        $staff = User::role(StaffController::ROLES)->where('hotel_id', Hotel::current()->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $shifts = Shift::orderBy('start_time')->get();

        $entries = EmployeeShift::with('shift')
            ->whereBetween('date', [$start->toDateString(), $start->copy()->addDays(6)->toDateString()])
            ->get()
            ->keyBy(fn ($e) => $e->user_id . '|' . $e->date->toDateString());

        return view('backend.hms.roster.index', compact('start', 'days', 'staff', 'shifts', 'entries'));
    }

    /** Assign (or reassign) one employee to a shift on one or more days. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('hotel_id', Hotel::current()->id)],
            'shift_id' => ['required', Rule::exists('shifts', 'id')->where('hotel_id', Hotel::current()->id)],
            'date_from' => ['required', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ], [], ['user_id' => 'pegawai', 'shift_id' => 'shift', 'date_from' => 'tanggal mulai', 'date_to' => 'tanggal akhir']);

        abort_unless(User::find($data['user_id'])->hasAnyRole(StaffController::ROLES), 422);

        $from = Carbon::parse($data['date_from']);
        $to = Carbon::parse($data['date_to'] ?? $data['date_from']);

        if ($from->diffInDays($to) > 31) {
            return $this->fail($request, 'Maksimal 31 hari sekali input.');
        }

        $skipped = 0;
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $entry = EmployeeShift::firstOrNew(['user_id' => $data['user_id'], 'date' => $day->toDateString()]);

            // A shift that has already been worked is history - leave it.
            if ($entry->exists && $entry->status !== 'Pending') {
                $skipped++;
                continue;
            }

            $entry->shift_id = $data['shift_id'];
            $entry->status = 'Pending';
            $entry->save();
        }

        $message = 'Jadwal shift berhasil disimpan.';
        if ($skipped) {
            $message .= " {$skipped} hari dilewati karena shift-nya sudah berjalan.";
        }

        return $this->done($request, $message);
    }

    public function destroy(Request $request, EmployeeShift $employeeShift)
    {
        if ($employeeShift->status !== 'Pending') {
            return $this->fail($request, 'Shift yang sudah dimulai tidak bisa dihapus.');
        }

        $employeeShift->delete();

        return $this->done($request, 'Jadwal shift dihapus.');
    }
}
