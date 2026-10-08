@extends('backend.layout.app')
@section('title', 'Rekap Shift')

@section('content')
    <x-hms.toolbar title="Rekap Shift Pegawai" section="Laporan">
        <form method="GET" data-no-spinner class="d-flex gap-2">
            <input type="month" name="month" value="{{ $from->format('Y-m') }}" class="form-control form-control-sm form-control-solid" onchange="this.form.submit()">
        </form>
        <a href="{{ route('hms.reports.shifts', ['month' => $from->format('Y-m'), 'export' => 1]) }}" class="btn btn-sm btn-success">
            <i class="ki-outline ki-file-down fs-4"></i> Export Excel (CSV)
        </a>
    </x-hms.toolbar>

    <x-hms.content>
        <div class="card card-flush">
            <div class="card-body table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-4">
                    <thead>
                        <tr class="text-gray-500 fw-bold fs-7 text-uppercase">
                            <th>Pegawai</th><th class="text-end">Jadwal</th><th class="text-end">Hadir</th><th class="text-end">Tidak hadir</th><th class="text-end">Terlambat</th><th class="text-end">Total jam</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="fw-bold text-gray-900">{{ $row['name'] }}</td>
                                <td class="text-end">{{ $row['scheduled'] }}</td>
                                <td class="text-end">{{ $row['worked'] }}</td>
                                <td class="text-end {{ $row['absent'] ? 'text-danger' : '' }}">{{ $row['absent'] }}</td>
                                <td class="text-end {{ $row['late'] ? 'text-warning' : '' }}">{{ $row['late'] }}</td>
                                <td class="text-end">{{ number_format($row['hours'], 1, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-10">Tidak ada jadwal di bulan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="text-muted fs-7">Terlambat = clock-in lewat dari jam mulai shift + toleransi (diatur di Profil Hotel).</div>
            </div>
        </div>
    </x-hms.content>
@endsection
