<div class="row g-6">
    <div class="col-md-5">
        <img src="{{ $roomType->image_url }}" alt="" class="w-100 rounded object-fit-cover" style="max-height: 240px;">
    </div>
    <div class="col-md-7">
        <div class="fs-2 fw-bold text-gray-900">Rp {{ number_format($roomType->base_price, 0, ',', '.') }} <span class="fs-6 text-muted fw-semibold">/ malam</span></div>
        <div class="text-muted mb-4">Kapasitas {{ $roomType->capacity }} tamu &middot; {{ $roomType->rooms->count() }} kamar</div>
        <p class="text-gray-700">{{ $roomType->description ?: 'Belum ada deskripsi.' }}</p>
        <div class="d-flex flex-wrap gap-2">
            @foreach ($roomType->amenities ?? [] as $amenity)
                <span class="badge badge-light-primary">{{ $amenity }}</span>
            @endforeach
        </div>
    </div>
</div>

<div class="separator my-6"></div>
<h5 class="mb-4">Daftar kamar</h5>
<div class="d-flex flex-wrap gap-3">
    @forelse ($roomType->rooms->sortBy('room_number') as $room)
        <span class="badge badge-lg badge-light-{{ ['Available' => 'success', 'Occupied' => 'danger', 'Cleaning' => 'warning'][$room->status] ?? 'secondary' }}">
            {{ $room->room_number }} &middot; {{ $room->status }}
        </span>
    @empty
        <span class="text-muted">Belum ada kamar untuk tipe ini.</span>
    @endforelse
</div>
