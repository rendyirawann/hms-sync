<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    use RespondsWithAlert;

    public function index(Request $request)
    {
        $rooms = Room::query()
            ->with(['roomType', 'activeReservation.booking.user'])
            ->when($request->filled('q'), fn ($q) => $q->where('room_number', 'ilike', '%' . $request->q . '%'))
            ->when($request->filled('type'), fn ($q) => $q->where('room_type_id', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('room_number')
            ->paginate(20)
            ->withQueryString();

        $roomTypes = RoomType::orderBy('name')->get(['id', 'name']);
        $summary = Room::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('backend.hms.rooms.index', compact('rooms', 'roomTypes', 'summary'));
    }

    public function store(Request $request)
    {
        Room::create($this->validated($request));

        return $this->done($request, 'Kamar berhasil ditambahkan.');
    }

    public function update(Request $request, Room $room)
    {
        $data = $this->validated($request, $room);

        if ($room->activeReservation && $data['status'] !== 'Occupied') {
            return $this->fail($request, 'Kamar sedang ditempati tamu. Lakukan check-out terlebih dahulu.');
        }

        $room->update($data);

        return $this->done($request, 'Kamar berhasil diperbarui.');
    }

    /** Housekeeping quick action: a cleaned room goes back on sale. */
    public function markAvailable(Request $request, Room $room)
    {
        if ($room->activeReservation) {
            return $this->fail($request, 'Kamar sedang ditempati tamu.');
        }

        $room->update(['status' => 'Available']);

        return $this->done($request, 'Kamar ' . $room->room_number . ' siap digunakan.');
    }

    public function destroy(Request $request, Room $room)
    {
        if ($room->reservations()->exists()) {
            return $this->fail($request, 'Kamar sudah punya riwayat reservasi. Ubah statusnya ke Maintenance saja.');
        }

        $room->delete();

        return $this->done($request, 'Kamar berhasil dihapus.');
    }

    private function validated(Request $request, ?Room $room = null): array
    {
        return $request->validate([
            'room_type_id' => ['required', 'exists:room_types,id'],
            'room_number' => ['required', 'string', 'max:20', Rule::unique('rooms')->ignore($room?->id)],
            'floor' => ['nullable', 'string', 'max:10'],
            'status' => ['required', Rule::in(Room::STATUSES)],
        ], [], ['room_type_id' => 'tipe kamar', 'room_number' => 'nomor kamar', 'floor' => 'lantai']);
    }
}
