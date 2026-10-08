@extends('backend.layout.app')
@section('title', 'Master Shift')

@section('content')
    <x-hms.toolbar title="Master Shift" section="Data Master">
        <button type="button" class="btn btn-sm btn-primary" data-hms-drawer="#drawer-shift"
            data-title="Tambah Shift" data-action="{{ route('hms.shifts.store') }}" data-method="POST">
            <i class="ki-outline ki-plus fs-3"></i> Tambah Shift
        </button>
    </x-hms.toolbar>

    <x-hms.content>
        <div class="card card-flush">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase">
                                <th>Nama shift</th>
                                <th>Jam kerja</th>
                                <th>Dipakai di jadwal</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @forelse ($shifts as $shift)
                                <tr>
                                    <td class="text-gray-900 fw-bold">{{ $shift->name }}</td>
                                    <td>{{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }}
                                        @if ($shift->end_time < $shift->start_time)
                                            <span class="badge badge-light-info ms-2">lewat tengah malam</span>
                                        @endif
                                    </td>
                                    <td>{{ $shift->employee_shifts_count }}x</td>
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-sm btn-icon btn-light-primary" title="Ubah"
                                            data-hms-drawer="#drawer-shift" data-title="Ubah Shift"
                                            data-action="{{ route('hms.shifts.update', $shift) }}" data-method="PUT"
                                            data-record="{{ json_encode(['name' => $shift->name, 'start_time' => substr($shift->start_time, 0, 5), 'end_time' => substr($shift->end_time, 0, 5)]) }}">
                                            <i class="ki-outline ki-pencil fs-4"></i>
                                        </button>
                                        <x-hms.delete :action="route('hms.shifts.destroy', $shift)" />
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-10">Belum ada shift. Contoh: Pagi 07:00-15:00, Siang 15:00-23:00, Malam 23:00-07:00.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-hms.content>

    <x-hms.drawer id="drawer-shift" title="Tambah Shift" :action="route('hms.shifts.store')">
        <div>
            <label class="form-label required">Nama shift</label>
            <input type="text" name="name" class="form-control form-control-solid" placeholder="mis. Pagi">
        </div>
        <div class="row g-5">
            <div class="col-6">
                <label class="form-label required">Jam mulai</label>
                <input type="time" name="start_time" class="form-control form-control-solid">
            </div>
            <div class="col-6">
                <label class="form-label required">Jam selesai</label>
                <input type="time" name="end_time" class="form-control form-control-solid">
            </div>
        </div>
    </x-hms.drawer>
@endsection
