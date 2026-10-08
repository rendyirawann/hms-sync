@extends('backend.layout.app')
@section('title', 'Kamar')

@php
    $statusColor = ['Available' => 'success', 'Occupied' => 'danger', 'Cleaning' => 'warning', 'Maintenance' => 'secondary'];
    $statusLabel = ['Available' => 'Tersedia', 'Occupied' => 'Terisi', 'Cleaning' => 'Dibersihkan', 'Maintenance' => 'Perbaikan'];
@endphp

@section('content')
    <x-hms.toolbar title="Kamar" section="Data Master">
        <button type="button" class="btn btn-sm btn-primary" data-hms-drawer="#drawer-room"
            data-title="Tambah Kamar" data-action="{{ route('hms.rooms.store') }}" data-method="POST">
            <i class="ki-outline ki-plus fs-3"></i> Tambah Kamar
        </button>
    </x-hms.toolbar>

    <x-hms.content>
        <div class="row g-5 mb-5">
            @foreach ($statusLabel as $key => $label)
                <div class="col-6 col-lg-3">
                    <a href="{{ request()->fullUrlWithQuery(['status' => request('status') === $key ? null : $key, 'page' => null]) }}"
                        class="card card-flush h-100 {{ request('status') === $key ? 'border border-' . $statusColor[$key] : '' }}">
                        <div class="card-body py-5">
                            <div class="fs-2hx fw-bold text-{{ $statusColor[$key] }}">{{ $summary[$key] ?? 0 }}</div>
                            <div class="text-gray-600 fw-semibold">{{ $label }}</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="card card-flush">
            <div class="card-header py-5">
                <form method="GET" class="card-title d-flex flex-wrap gap-3" data-no-spinner>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-solid w-200px" placeholder="No. kamar">
                    <select name="type" class="form-select form-select-solid w-200px" onchange="this.form.submit()">
                        <option value="">Semua tipe</option>
                        @foreach ($roomTypes as $type)
                            <option value="{{ $type->id }}" @selected(request('type') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="status" value="{{ request('status') }}">
                </form>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase">
                                <th>No. Kamar</th>
                                <th>Lantai</th>
                                <th>Tipe</th>
                                <th>Status</th>
                                <th>Tamu saat ini</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @forelse ($rooms as $room)
                                <tr>
                                    <td class="text-gray-900 fw-bold">{{ $room->room_number }}</td>
                                    <td>{{ $room->floor ?: '-' }}</td>
                                    <td>{{ $room->roomType->name }}</td>
                                    <td><span class="badge badge-light-{{ $statusColor[$room->status] }}">{{ $statusLabel[$room->status] }}</span></td>
                                    <td>
                                        @if ($room->activeReservation)
                                            {{ $room->activeReservation->booking->user->name }}
                                            <div class="text-muted fs-7">s/d {{ $room->activeReservation->booking->check_out_date->translatedFormat('d M Y') }}</div>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @if ($room->status === 'Cleaning')
                                            <form method="POST" action="{{ route('hms.rooms.available', $room) }}" class="d-inline" data-ajax>
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light-success" data-loading-text="...">
                                                    <i class="ki-outline ki-check fs-4"></i> Siap
                                                </button>
                                            </form>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-icon btn-light-primary" title="Ubah"
                                            data-hms-drawer="#drawer-room" data-title="Ubah Kamar {{ $room->room_number }}"
                                            data-action="{{ route('hms.rooms.update', $room) }}" data-method="PUT"
                                            data-record="{{ json_encode($room->only('room_type_id', 'room_number', 'floor', 'status')) }}">
                                            <i class="ki-outline ki-pencil fs-4"></i>
                                        </button>
                                        <x-hms.delete :action="route('hms.rooms.destroy', $room)" />
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-10">Belum ada kamar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $rooms->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </x-hms.content>

    <x-hms.drawer id="drawer-room" title="Tambah Kamar" :action="route('hms.rooms.store')">
        <div>
            <label class="form-label required">Tipe kamar</label>
            <select name="room_type_id" class="form-select form-select-solid">
                <option value="">Pilih tipe</option>
                @foreach ($roomTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="row g-5">
            <div class="col-7">
                <label class="form-label required">Nomor kamar</label>
                <input type="text" name="room_number" class="form-control form-control-solid" placeholder="mis. 101">
            </div>
            <div class="col-5">
                <label class="form-label">Lantai</label>
                <input type="text" name="floor" class="form-control form-control-solid">
            </div>
        </div>
        <div>
            <label class="form-label required">Status</label>
            <select name="status" class="form-select form-select-solid">
                @foreach ($statusLabel as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="form-text">Status "Terisi" diatur otomatis saat check-in / check-out.</div>
        </div>
    </x-hms.drawer>
@endsection
