@extends('backend.layout.app')
@section('title', 'Laporan')

@php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp

@section('content')
    <x-hms.toolbar title="Laporan Hotel" section="Laporan">
        <form method="GET" data-no-spinner class="d-flex gap-2">
            <input type="month" name="month" value="{{ $from->format('Y-m') }}" class="form-control form-control-sm form-control-solid" onchange="this.form.submit()">
        </form>
        <a href="{{ route('hms.reports.shifts', ['month' => $from->format('Y-m')]) }}" class="btn btn-sm btn-light">Rekap shift</a>
    </x-hms.toolbar>

    <x-hms.content>
        <h5 class="text-gray-600 mb-4">Hari ini</h5>
        <div class="row g-5 mb-8">
            @foreach ([
                ['Okupansi', $today['occupancy'] . '%', 'primary'],
                ['Tamu menginap', $today['in_house'] . ' / ' . $today['rooms'], 'success'],
                ['Kedatangan', $today['arrivals'], 'info'],
                ['Keberangkatan', $today['departures'], 'warning'],
            ] as [$label, $value, $color])
                <div class="col-6 col-lg-3">
                    <div class="card card-flush h-100"><div class="card-body py-5">
                        <div class="fs-2hx fw-bold text-{{ $color }}">{{ $value }}</div>
                        <div class="text-gray-600 fw-semibold">{{ $label }}</div>
                    </div></div>
                </div>
            @endforeach
        </div>

        <h5 class="text-gray-600 mb-4">{{ $from->translatedFormat('F Y') }}</h5>
        <div class="row g-5 mb-6">
            @foreach ([
                ['Okupansi', $metrics['occupancy'] . '%', $metrics['sold_nights'] . ' kamar-malam terjual'],
                ['ADR', $rp($metrics['adr']), 'Rata-rata harga kamar / malam'],
                ['RevPAR', $rp($metrics['revpar']), 'Pendapatan per kamar tersedia'],
                ['Pendapatan kamar', $rp($metrics['room_revenue']), 'Diakui per malam menginap'],
                ['Uang masuk', $rp($metrics['collected']), 'Pembayaran lunas bulan ini'],
                ['Booking baru', $metrics['bookings'], $metrics['cancelled'] . ' batal / kedaluwarsa / no-show'],
            ] as [$label, $value, $hint])
                <div class="col-6 col-xl-2">
                    <div class="card card-flush h-100"><div class="card-body py-5">
                        <div class="text-gray-600 fw-semibold fs-7">{{ $label }}</div>
                        <div class="fs-3 fw-bold text-gray-900 my-1">{{ $value }}</div>
                        <div class="text-muted fs-8">{{ $hint }}</div>
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="row g-6">
            <div class="col-xl-8">
                <div class="card card-flush h-100">
                    <div class="card-header"><h3 class="card-title">Okupansi &amp; uang masuk harian</h3></div>
                    <div class="card-body"><div id="report-chart" style="height: 320px"></div></div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card card-flush h-100">
                    <div class="card-header"><h3 class="card-title">Per tipe kamar</h3></div>
                    <div class="card-body pt-0">
                        <table class="table table-row-dashed fs-7 gy-3">
                            <thead><tr class="text-gray-500 fw-bold"><th>Tipe</th><th class="text-end">Malam</th><th class="text-end">Pendapatan</th></tr></thead>
                            <tbody>
                                @forelse ($byType as $name => $row)
                                    <tr><td class="fw-bold">{{ $name }}</td><td class="text-end">{{ $row['nights'] }}</td><td class="text-end">{{ $rp($row['revenue']) }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-6">Belum ada data.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </x-hms.content>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var host = document.getElementById('report-chart');
            if (!host || typeof ApexCharts === 'undefined') { return; }
            var data = @json($chart);
            new ApexCharts(host, {
                chart: { type: 'line', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [
                    { name: 'Okupansi (%)', type: 'column', data: data.occupancy },
                    { name: 'Uang masuk', type: 'line', data: data.revenue }
                ],
                xaxis: { categories: data.labels },
                yaxis: [
                    { max: 100, min: 0, title: { text: 'Okupansi %' } },
                    { opposite: true, labels: { formatter: function (v) { return 'Rp ' + Math.round(v).toLocaleString('id-ID'); } } }
                ],
                stroke: { width: [0, 3], curve: 'smooth' },
                dataLabels: { enabled: false },
                legend: { position: 'top' }
            }).render();
        });
    </script>
@endpush
