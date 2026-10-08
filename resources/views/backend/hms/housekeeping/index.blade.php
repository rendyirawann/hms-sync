@extends('backend.layout.app')
@section('title', 'Housekeeping')

@section('content')
    <x-hms.toolbar title="Housekeeping" section="Operasional">
        <a href="{{ route('hms.housekeeping.index', request()->boolean('mine') ? [] : ['mine' => 1]) }}" class="btn btn-sm btn-light">
            {{ request()->boolean('mine') ? 'Semua tugas' : 'Tugas saya' }}
        </a>
        <button type="button" class="btn btn-sm btn-primary" data-hms-drawer="#drawer-task" data-title="Buat Tugas">
            <i class="ki-outline ki-plus fs-3"></i> Buat tugas
        </button>
    </x-hms.toolbar>

    <x-hms.content>
        <div class="row g-6">
            @foreach (['pending' => ['Menunggu', 'warning'], 'in_progress' => ['Dikerjakan', 'primary']] as $status => [$label, $color])
                <div class="col-lg-6">
                    <div class="card card-flush h-100">
                        <div class="card-header">
                            <h3 class="card-title">{{ $label }} <span class="badge badge-light-{{ $color }} ms-2">{{ ($open[$status] ?? collect())->count() }}</span></h3>
                        </div>
                        <div class="card-body pt-0 d-flex flex-column gap-4">
                            @forelse ($open[$status] ?? [] as $task)
                                <div class="border border-dashed rounded p-4">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fs-3 fw-bold text-gray-900">Kamar {{ $task->room->room_number }}</div>
                                            <div class="text-muted fs-7">{{ $task->room->roomType->name ?? '' }}{{ $task->room->floor ? ' · Lantai ' . $task->room->floor : '' }}</div>
                                        </div>
                                        <span class="badge badge-light-{{ $task->type === 'maintenance' ? 'danger' : 'info' }}">{{ \App\Models\HousekeepingTask::TYPES[$task->type] ?? $task->type }}</span>
                                    </div>
                                    @if ($task->notes)
                                        <div class="text-gray-700 fs-7 mt-2">{{ $task->notes }}</div>
                                    @endif
                                    <div class="text-muted fs-8 mt-2">
                                        Dibuat {{ $task->created_at->diffForHumans() }}
                                        @if ($task->started_at) &middot; mulai {{ $task->started_at->format('H:i') }} @endif
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                                        <form method="POST" action="{{ route('hms.housekeeping.assign', $task) }}" data-ajax class="d-flex gap-2">
                                            @csrf
                                            <select name="assigned_to" class="form-select form-select-sm form-select-solid w-175px" onchange="this.form.requestSubmit()">
                                                <option value="">— Petugas —</option>
                                                @foreach ($staff as $person)
                                                    <option value="{{ $person->id }}" @selected($task->assigned_to === $person->id)>{{ $person->name }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                        @if ($task->status === 'pending')
                                            <form method="POST" action="{{ route('hms.housekeeping.start', $task) }}" data-ajax>
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light-primary">Mulai</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('hms.housekeeping.finish', $task) }}" data-ajax>
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">Selesai</button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted text-center py-8">Tidak ada tugas.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card card-flush mt-6">
            <div class="card-header"><h3 class="card-title">Riwayat terakhir</h3></div>
            <div class="card-body pt-0 table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-3">
                    <thead><tr class="text-gray-500 fw-bold text-uppercase"><th>Kamar</th><th>Jenis</th><th>Petugas</th><th>Mulai</th><th>Selesai</th><th>Durasi</th></tr></thead>
                    <tbody class="fw-semibold text-gray-700">
                        @forelse ($history as $task)
                            <tr>
                                <td class="fw-bold">{{ $task->room->room_number }}</td>
                                <td>{{ \App\Models\HousekeepingTask::TYPES[$task->type] ?? $task->type }}</td>
                                <td>{{ $task->assignee->name ?? '-' }}</td>
                                <td>{{ $task->started_at?->format('d/m H:i') }}</td>
                                <td>{{ $task->finished_at?->format('d/m H:i') }}</td>
                                <td>{{ $task->started_at && $task->finished_at ? $task->started_at->diffInMinutes($task->finished_at) . ' mnt' : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-6">Belum ada riwayat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-hms.content>

    <x-hms.drawer id="drawer-task" title="Buat Tugas" :action="route('hms.housekeeping.store')">
        <div>
            <label class="form-label required">Kamar</label>
            <select name="room_id" class="form-select form-select-solid">
                <option value="">Pilih kamar</option>
                @foreach ($rooms as $room)
                    <option value="{{ $room->id }}">{{ $room->room_number }} ({{ $room->status }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label required">Jenis tugas</label>
            <select name="type" class="form-select form-select-solid">
                @foreach (\App\Models\HousekeepingTask::TYPES as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="form-text">Perbaikan membuat kamar berstatus Maintenance (tidak dijual) sampai selesai.</div>
        </div>
        <div>
            <label class="form-label">Petugas</label>
            <select name="assigned_to" class="form-select form-select-solid">
                <option value="">Belum ditentukan</option>
                @foreach ($staff as $person)
                    <option value="{{ $person->id }}">{{ $person->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Catatan</label>
            <input type="text" name="notes" class="form-control form-control-solid">
        </div>
    </x-hms.drawer>
@endsection
