<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    use RespondsWithAlert;

    public function index()
    {
        $shifts = Shift::withCount('employeeShifts')->orderBy('start_time')->get();

        return view('backend.hms.shifts.index', compact('shifts'));
    }

    public function store(Request $request)
    {
        Shift::create($this->validated($request));

        return $this->done($request, 'Shift berhasil ditambahkan.');
    }

    public function update(Request $request, Shift $shift)
    {
        $shift->update($this->validated($request));

        return $this->done($request, 'Shift berhasil diperbarui.');
    }

    public function destroy(Request $request, Shift $shift)
    {
        if ($shift->employeeShifts()->exists()) {
            return $this->fail($request, 'Shift sudah dipakai di jadwal pegawai dan tidak bisa dihapus.');
        }

        $shift->delete();

        return $this->done($request, 'Shift berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        // end_time may be earlier than start_time (night shift past midnight),
        // so only the format is checked.
        return $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
        ], [], ['name' => 'nama shift', 'start_time' => 'jam mulai', 'end_time' => 'jam selesai']);
    }
}
