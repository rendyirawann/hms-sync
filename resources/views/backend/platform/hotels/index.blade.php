@extends('backend.layout.app')
@section('title', 'Hotel Mitra')

@php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp

@section('content')
    <x-hms.toolbar title="Hotel Mitra" section="Platform">
        <button type="button" class="btn btn-sm btn-primary" data-hms-drawer="#drawer-hotel" data-title="Tambah Hotel">
            <i class="ki-outline ki-plus fs-3"></i> Tambah hotel
        </button>
    </x-hms.toolbar>

    <x-hms.content>
        <div class="row g-5 mb-5">
            @foreach ([
                ['Menunggu persetujuan', $counts['pending'] ?? 0, 'warning', 'pending'],
                ['Tayang', \App\Models\Hotel::listed()->count(), 'success', 'active'],
                ['Dinonaktifkan', \App\Models\Hotel::where('approval_status', 'approved')->where('status', false)->count(), 'secondary', 'inactive'],
                ['Ditolak', $counts['rejected'] ?? 0, 'danger', 'rejected'],
            ] as [$label, $value, $color, $filter])
                <div class="col-6 col-lg-3">
                    <a href="{{ route('platform.hotels.index', request('status') === $filter ? [] : ['status' => $filter]) }}"
                        class="card card-flush h-100 {{ request('status') === $filter ? 'border border-' . $color : '' }}">
                        <div class="card-body py-5">
                            <div class="fs-2hx fw-bold text-{{ $color }}">{{ $value }}</div>
                            <div class="text-gray-600 fw-semibold">{{ $label }}</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="card card-flush">
            <div class="card-header py-5">
                <form method="GET" class="card-title" data-no-spinner>
                    <input type="hidden" name="status" value="{{ request('status') }}">
                    <div class="d-flex align-items-center position-relative">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-solid w-250px ps-12" placeholder="Nama hotel atau kota">
                    </div>
                </form>
            </div>
            <div class="card-body pt-0 table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-4">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase">
                            <th>Hotel</th><th>Pemilik</th><th>Kamar</th><th>Komisi</th><th>30 hari terakhir</th><th>Status</th><th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                        @forelse ($hotels as $hotel)
                            @php $s = $stats[$hotel->id] ?? null; @endphp
                            <tr>
                                <td>
                                    <div class="text-gray-900 fw-bold">{{ $hotel->name }}</div>
                                    <div class="text-muted fs-7">{{ $hotel->city ?: '-' }} &middot; <a href="{{ route('hotels.show', $hotel) }}" target="_blank" rel="noopener">/hotel/{{ $hotel->slug }}</a></div>
                                </td>
                                <td>{{ $hotel->owner?->name ?? '-' }}<div class="text-muted fs-7">{{ $hotel->owner?->email }}</div></td>
                                <td>{{ $hotel->rooms_count }} <span class="text-muted fs-7">({{ $hotel->room_types_count }} tipe)</span></td>
                                <td>{{ (float) $hotel->commission_percent }}%</td>
                                <td>{{ $s?->bookings ?? 0 }} booking<div class="text-muted fs-7">{{ $rp($s?->revenue ?? 0) }}</div></td>
                                <td><span class="badge badge-light-{{ $hotel->approval_badge }}">{{ $hotel->approval_label }}</span></td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-light-primary" data-hms-show="{{ route('platform.hotels.show', $hotel) }}" data-title="{{ $hotel->name }}">Detail</button>
                                    <form method="POST" action="{{ route('platform.hotels.enter', $hotel) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary" data-loading-text="...">Kelola</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-10">Belum ada hotel.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $hotels->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </x-hms.content>

    <x-hms.drawer id="drawer-hotel" title="Tambah Hotel" :action="route('platform.hotels.store')" width="560px">
        <div class="notice bg-light-primary rounded p-4 fs-7">Hotel yang dibuat platform langsung disetujui. Pemilik login dengan email &amp; password di bawah untuk mengisi kamar.</div>
        <div>
            <label class="form-label required">Nama hotel</label>
            <input type="text" name="name" class="form-control form-control-solid">
        </div>
        <div class="row g-5">
            <div class="col-7">
                <label class="form-label required">Kota</label>
                <input type="text" name="city" class="form-control form-control-solid">
            </div>
            <div class="col-5">
                <label class="form-label required">Komisi (%)</label>
                <input type="number" name="commission_percent" step="0.5" min="0" max="100" value="{{ \App\Models\Setting::get('platform_default_commission', '10') }}" class="form-control form-control-solid">
            </div>
        </div>
        <div>
            <label class="form-label required">Alamat</label>
            <textarea name="address" rows="2" class="form-control form-control-solid"></textarea>
        </div>
        <div>
            <label class="form-label">Telepon hotel</label>
            <input type="text" name="phone" class="form-control form-control-solid">
        </div>
        <div class="separator"></div>
        <div>
            <label class="form-label required">Nama pemilik / admin hotel</label>
            <input type="text" name="owner_name" class="form-control form-control-solid">
        </div>
        <div class="row g-5">
            <div class="col-6">
                <label class="form-label required">Email pemilik</label>
                <input type="email" name="owner_email" class="form-control form-control-solid">
            </div>
            <div class="col-6">
                <label class="form-label required">Password awal</label>
                <input type="password" name="owner_password" autocomplete="new-password" class="form-control form-control-solid">
            </div>
        </div>
    </x-hms.drawer>
@endsection
