@extends('backend.layout.app')
@section('title', 'Tipe Kamar')

@section('content')
    <x-hms.toolbar title="Tipe Kamar" section="Data Master">
        <button type="button" class="btn btn-sm btn-primary" data-hms-drawer="#drawer-room-type"
            data-title="Tambah Tipe Kamar" data-action="{{ route('hms.room-types.store') }}" data-method="POST">
            <i class="ki-outline ki-plus fs-3"></i> Tambah Tipe
        </button>
    </x-hms.toolbar>

    <x-hms.content>
        <div class="card card-flush">
            <div class="card-header py-5">
                <form method="GET" class="card-title" data-no-spinner>
                    <div class="d-flex align-items-center position-relative">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-solid w-250px ps-12" placeholder="Cari tipe kamar">
                    </div>
                </form>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase">
                                <th>Tipe</th>
                                <th>Kapasitas</th>
                                <th>Harga / malam</th>
                                <th>Jumlah kamar</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @forelse ($roomTypes as $type)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-4">
                                            <img src="{{ $type->image_url }}" alt="" class="rounded w-70px h-50px object-fit-cover">
                                            <div>
                                                <div class="text-gray-900 fw-bold">{{ $type->name }}</div>
                                                <div class="text-muted fs-7">{{ implode(', ', array_slice($type->amenities ?? [], 0, 3)) }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $type->capacity }} tamu</td>
                                    <td>Rp {{ number_format($type->base_price, 0, ',', '.') }}</td>
                                    <td>{{ $type->rooms_count }}</td>
                                    <td>
                                        <span class="badge badge-light-{{ $type->is_active ? 'success' : 'secondary' }}">
                                            {{ $type->is_active ? 'Dijual' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-sm btn-icon btn-light-info" title="Detail"
                                            data-hms-show="{{ route('hms.room-types.show', $type) }}" data-title="{{ $type->name }}">
                                            <i class="ki-outline ki-eye fs-4"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-icon btn-light-primary" title="Ubah"
                                            data-hms-drawer="#drawer-room-type" data-title="Ubah Tipe Kamar"
                                            data-action="{{ route('hms.room-types.update', $type) }}" data-method="PUT"
                                            data-record="{{ json_encode($type->only('name', 'description', 'capacity', 'base_price', 'is_active') + ['amenities_text' => implode(', ', $type->amenities ?? [])]) }}">
                                            <i class="ki-outline ki-pencil fs-4"></i>
                                        </button>
                                        <x-hms.delete :action="route('hms.room-types.destroy', $type)" />
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-10">Belum ada tipe kamar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $roomTypes->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </x-hms.content>

    <x-hms.drawer id="drawer-room-type" title="Tambah Tipe Kamar" :action="route('hms.room-types.store')" :files="true">
        <div>
            <label class="form-label required">Nama tipe</label>
            <input type="text" name="name" class="form-control form-control-solid" placeholder="mis. Deluxe Double">
        </div>
        <div class="row g-5">
            <div class="col-6">
                <label class="form-label required">Kapasitas (tamu)</label>
                <input type="number" name="capacity" min="1" max="20" value="2" class="form-control form-control-solid">
            </div>
            <div class="col-6">
                <label class="form-label required">Harga / malam (Rp)</label>
                <input type="number" name="base_price" min="0" step="1000" class="form-control form-control-solid">
            </div>
        </div>
        <div>
            <label class="form-label">Fasilitas</label>
            <input type="text" name="amenities_text" class="form-control form-control-solid" placeholder="WiFi, AC, TV, Sarapan">
            <div class="form-text">Pisahkan dengan koma.</div>
        </div>
        <div>
            <label class="form-label">Deskripsi</label>
            <textarea name="description" rows="4" class="form-control form-control-solid"></textarea>
        </div>
        <div>
            <label class="form-label">Foto</label>
            <input type="file" name="image" accept="image/*" class="form-control form-control-solid">
            <div class="form-text">Maks. 2 MB. Kosongkan jika tidak ingin mengganti.</div>
        </div>
        <label class="form-check form-switch form-check-custom form-check-solid">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
            <span class="form-check-label">Tampilkan &amp; jual di website</span>
        </label>
    </x-hms.drawer>
@endsection
