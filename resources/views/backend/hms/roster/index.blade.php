@extends('backend.layout.app')
@section('title', 'Jadwal Pegawai')

@section('content')
    <x-hms.toolbar title="Jadwal Pegawai" section="Shift">
        <a href="{{ route('hms.roster.index', ['week' => $start->copy()->subWeek()->toDateString()]) }}" class="btn btn-sm btn-light"><i class="ki-outline ki-left fs-4"></i></a>
        <span class="fw-bold text-gray-800 px-2">{{ $start->translatedFormat('d M') }} - {{ $start->copy()->addDays(6)->translatedFormat('d M Y') }}</span>
        <a href="{{ route('hms.roster.index', ['week' => $start->copy()->addWeek()->toDateString()]) }}" class="btn btn-sm btn-light"><i class="ki-outline ki-right fs-4"></i></a>
        <button type="button" class="btn btn-sm btn-primary" data-hms-drawer="#drawer-roster" data-title="Atur Shift" data-method="POST">
            <i class="ki-outline ki-plus fs-3"></i> Atur Shift
        </button>
    </x-hms.toolbar>

    <x-hms.content>
        <div class="card card-flush">
            <div class="card-body">
                @if ($shifts->isEmpty())
                    <div class="alert alert-warning">Belum ada master shift. Buat dulu di <a href="{{ route('hms.shifts.index') }}">Master Shift</a>.</div>
                @endif
                <div class="table-responsive">
                    <table class="table table-bordered align-middle fs-7 gy-3 text-center">
                        <thead>
                            <tr class="fw-bold text-gray-600 bg-light">
                                <th class="text-start min-w-150px">Pegawai</th>
                                @foreach ($days as $day)
                                    <th class="min-w-100px {{ $day->isToday() ? 'text-primary' : '' }}">{{ $day->translatedFormat('D') }}<br>{{ $day->format('d/m') }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($staff as $person)
                                <tr>
                                    <td class="text-start fw-bold text-gray-800">{{ $person->name }}</td>
                                    @foreach ($days as $day)
                                        @php $entry = $entries[$person->id . '|' . $day->toDateString()] ?? null; @endphp
                                        <td>
                                            @if ($entry)
                                                <span class="badge badge-light-{{ $entry->status_badge }} mb-1">{{ $entry->shift->name }}</span>
                                                <div class="text-muted">{{ substr($entry->shift->start_time, 0, 5) }}-{{ substr($entry->shift->end_time, 0, 5) }}</div>
                                                @if ($entry->status === 'Pending')
                                                    <form method="POST" action="{{ route('hms.roster.destroy', $entry) }}" data-ajax data-ajax-confirm="Hapus jadwal ini?">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn btn-link btn-sm p-0 text-danger fs-8" data-loading-text="...">hapus</button>
                                                    </form>
                                                @else
                                                    <div class="text-muted fs-8">{{ $entry->status === 'Started' ? 'Sedang bertugas' : 'Selesai' }}</div>
                                                @endif
                                            @else
                                                <span class="text-gray-300">Libur</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-muted py-10">Belum ada pegawai aktif.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-hms.content>

    <x-hms.drawer id="drawer-roster" title="Atur Shift" :action="route('hms.roster.store')">
        <div>
            <label class="form-label required">Pegawai</label>
            <select name="user_id" class="form-select form-select-solid">
                <option value="">Pilih pegawai</option>
                @foreach ($staff as $person)
                    <option value="{{ $person->id }}">{{ $person->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label required">Shift</label>
            <select name="shift_id" class="form-select form-select-solid">
                <option value="">Pilih shift</option>
                @foreach ($shifts as $shift)
                    <option value="{{ $shift->id }}">{{ $shift->label }}</option>
                @endforeach
            </select>
        </div>
        <div class="row g-5">
            <div class="col-6">
                <label class="form-label required">Dari tanggal</label>
                <input type="date" name="date_from" value="{{ today()->toDateString() }}" class="form-control form-control-solid">
            </div>
            <div class="col-6">
                <label class="form-label">Sampai tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-solid">
            </div>
        </div>
        <div class="form-text">Jadwal yang sudah ada di tanggal tersebut akan diganti (kecuali shift yang sudah berjalan).</div>
    </x-hms.drawer>
@endsection
