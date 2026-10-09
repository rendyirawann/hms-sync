<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\HousekeepingTask;
use App\Models\Room;
use App\Models\User;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Housekeeping board. Finishing a checkout/maintenance task puts the room
 * back on sale (status Available) unless a guest is in it.
 */
class HousekeepingController extends Controller
{
    use RespondsWithAlert;

    public function index(Request $request)
    {
        $open = HousekeepingTask::with(['room:id,room_number,floor,status,room_type_id', 'room.roomType:id,name', 'assignee:id,name'])
            ->where('status', '!=', 'done')
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->orderByRaw("case status when 'in_progress' then 0 else 1 end")
            ->oldest()
            ->get()
            ->groupBy('status');

        $history = HousekeepingTask::with(['room:id,room_number', 'assignee:id,name'])
            ->where('status', 'done')
            ->latest('finished_at')
            ->limit(30)
            ->get();

        return view('backend.hms.housekeeping.index', [
            'open' => $open,
            'history' => $history,
            'staff' => User::role(StaffController::ROLES)->where('hotel_id', Hotel::current()->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'rooms' => Room::orderBy('room_number')->get(['id', 'room_number', 'status']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'id')->where('hotel_id', Hotel::current()->id)],
            'type' => ['required', Rule::in(array_keys(HousekeepingTask::TYPES))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('hotel_id', Hotel::current()->id)],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['room_id' => 'kamar', 'type' => 'jenis tugas', 'assigned_to' => 'petugas']);

        DB::transaction(function () use ($data, $request) {
            HousekeepingTask::create($data + ['status' => 'pending', 'created_by' => $request->user()->id]);

            $room = Room::find($data['room_id']);
            if ($data['type'] === 'maintenance' && ! $room->activeReservation()->exists()) {
                $room->update(['status' => 'Maintenance']);
            }
        });

        return $this->done($request, 'Tugas housekeeping dibuat.');
    }

    public function assign(Request $request, HousekeepingTask $task)
    {
        $request->validate(['assigned_to' => ['nullable', Rule::exists('users', 'id')->where('hotel_id', Hotel::current()->id)]]);
        $task->update(['assigned_to' => $request->input('assigned_to') ?: $request->user()->id]);

        return $this->done($request, 'Petugas diperbarui.');
    }

    public function start(Request $request, HousekeepingTask $task)
    {
        if ($task->status !== 'pending') {
            return $this->fail($request, 'Tugas ini sudah dimulai.');
        }

        $task->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'assigned_to' => $task->assigned_to ?? $request->user()->id,
        ]);

        return $this->done($request, 'Tugas kamar ' . $task->room->room_number . ' dimulai.');
    }

    public function finish(Request $request, HousekeepingTask $task)
    {
        if ($task->status === 'done') {
            return $this->fail($request, 'Tugas ini sudah selesai.');
        }

        DB::transaction(function () use ($task, $request) {
            $task->update([
                'status' => 'done',
                'started_at' => $task->started_at ?? now(),
                'finished_at' => now(),
                'assigned_to' => $task->assigned_to ?? $request->user()->id,
            ]);

            $room = Room::whereKey($task->room_id)->lockForUpdate()->first();
            $stillOpen = $room->housekeepingTasks()->where('status', '!=', 'done')->exists();

            if (! $stillOpen && ! $room->activeReservation()->exists() && in_array($room->status, ['Cleaning', 'Maintenance'], true)) {
                $room->update(['status' => 'Available']);
            }
        });

        return $this->done($request, 'Kamar ' . $task->room->room_number . ' selesai dikerjakan.');
    }
}
