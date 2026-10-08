<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomTypeController extends Controller
{
    use RespondsWithAlert;

    public function index(Request $request)
    {
        $roomTypes = RoomType::query()
            ->withCount('rooms')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'ilike', '%' . $request->q . '%'))
            ->orderBy('base_price')
            ->paginate(15)
            ->withQueryString();

        return view('backend.hms.room_types.index', compact('roomTypes'));
    }

    public function show(RoomType $roomType)
    {
        $roomType->load('rooms');

        return view('backend.hms.room_types._show', compact('roomType'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['hotel_id'] = Hotel::current()->id;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('room-types', 'public');
        }

        RoomType::create($data);

        return $this->done($request, 'Tipe kamar berhasil ditambahkan.');
    }

    public function update(Request $request, RoomType $roomType)
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            if ($roomType->image) {
                Storage::disk('public')->delete($roomType->image);
            }
            $data['image'] = $request->file('image')->store('room-types', 'public');
        }

        $roomType->update($data);

        return $this->done($request, 'Tipe kamar berhasil diperbarui.');
    }

    public function destroy(Request $request, RoomType $roomType)
    {
        if ($roomType->bookings()->exists()) {
            return $this->fail($request, 'Tipe kamar sudah punya riwayat booking. Nonaktifkan saja agar tidak dijual.');
        }

        if ($roomType->image) {
            Storage::disk('public')->delete($roomType->image);
        }
        $roomType->delete();

        return $this->done($request, 'Tipe kamar berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'amenities_text' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:2048'],
        ], [], [
            'name' => 'nama tipe', 'capacity' => 'kapasitas', 'base_price' => 'harga per malam',
            'amenities_text' => 'fasilitas', 'image' => 'foto',
        ]);

        $data['amenities'] = collect(explode(',', (string) ($data['amenities_text'] ?? '')))
            ->map(fn ($a) => trim($a))->filter()->values()->all();
        $data['is_active'] = $request->boolean('is_active');
        unset($data['amenities_text'], $data['image']);

        return $data;
    }
}
