@extends('backend.layout.app')
@section('title', 'Setoran ke Hotel')

@php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp

@section('content')
    <x-hms.toolbar title="Setoran ke Hotel" section="Platform" />

    <x-hms.content>
        <div class="row g-5 mb-6">
            <div class="col-md-4"><div class="card card-flush h-100"><div class="card-body py-5">
                <div class="text-gray-600 fw-semibold fs-7">Harus dibayar ke hotel</div>
                <div class="fs-2 fw-bold text-primary">{{ $rp($summary['unpaid']) }}</div>
            </div></div></div>
            <div class="col-md-4"><div class="card card-flush h-100"><div class="card-body py-5">
                <div class="text-gray-600 fw-semibold fs-7">Komisi terutang dari hotel (tunai)</div>
                <div class="fs-2 fw-bold text-warning">{{ $rp($summary['owed']) }}</div>
            </div></div></div>
            <div class="col-md-4"><div class="card card-flush h-100"><div class="card-body py-5">
                <div class="text-gray-600 fw-semibold fs-7">Komisi bulan ini</div>
                <div class="fs-2 fw-bold text-success">{{ $rp($summary['commission_month']) }}</div>
            </div></div></div>
        </div>

        <div class="card card-flush mb-6">
            <div class="card-header py-5">
                <div class="card-title flex-column">
                    <h3 class="mb-1">Buat setoran</h3>
                    <span class="text-muted fs-7">Stay yang selesai (check-out) dalam periode dan belum pernah disetor.</span>
                </div>
                <div class="card-toolbar">
                    <form method="GET" class="d-flex gap-2" data-no-spinner>
                        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm form-control-solid">
                        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm form-control-solid">
                        <button type="submit" class="btn btn-sm btn-light">Hitung</button>
                    </form>
                </div>
            </div>
            <div class="card-body pt-0">
                @if ($previews->isEmpty())
                    <div class="text-muted py-6 text-center">Tidak ada stay yang belum disetor pada {{ $from->format('d/m/Y') }} - {{ $to->format('d/m/Y') }}.</div>
                @else
                    <form method="POST" action="{{ route('platform.settlements.store') }}" data-ajax>
                        @csrf
                        <input type="hidden" name="period_start" value="{{ $from->toDateString() }}">
                        <input type="hidden" name="period_end" value="{{ $to->toDateString() }}">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-7 gy-3">
                                <thead><tr class="text-gray-500 fw-bold text-uppercase"><th></th><th>Hotel</th><th class="text-end">Stay</th><th class="text-end">Diterima platform</th><th class="text-end">Diterima hotel</th><th class="text-end">Komisi</th><th class="text-end">Setoran</th></tr></thead>
                                <tbody>
                                    @foreach ($previews as $hotelId => $p)
                                        <tr>
                                            <td><input type="checkbox" name="hotel_ids[]" value="{{ $hotelId }}" class="form-check-input" checked></td>
                                            <td class="fw-bold">{{ $hotels->firstWhere('id', $hotelId)?->name }}</td>
                                            <td class="text-end">{{ $p['bookings_count'] }}</td>
                                            <td class="text-end">{{ $rp($p['collected_by_platform']) }}</td>
                                            <td class="text-end">{{ $rp($p['collected_by_hotel']) }}</td>
                                            <td class="text-end">{{ $rp($p['commission_amount']) }}</td>
                                            <td class="text-end fw-bold {{ $p['payout_amount'] < 0 ? 'text-warning' : 'text-primary' }}">{{ $rp($p['payout_amount']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end"><button type="submit" class="btn btn-primary">Buat setoran terpilih</button></div>
                    </form>
                @endif
            </div>
        </div>

        <div class="card card-flush">
            <div class="card-header"><h3 class="card-title">Riwayat setoran</h3></div>
            <div class="card-body pt-0 table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-4">
                    <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase"><th>Hotel</th><th>Periode</th><th class="text-end">Stay</th><th class="text-end">Komisi</th><th class="text-end">Setoran</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody class="fw-semibold text-gray-700">
                        @forelse ($settlements as $s)
                            <tr>
                                <td class="fw-bold text-gray-900">{{ $s->hotel?->name }}</td>
                                <td>{{ $s->period_start->format('d/m/Y') }} - {{ $s->period_end->format('d/m/Y') }}</td>
                                <td class="text-end">{{ $s->bookings_count }}</td>
                                <td class="text-end">{{ $rp($s->commission_amount) }}</td>
                                <td class="text-end fw-bold {{ $s->payout_amount < 0 ? 'text-warning' : '' }}">{{ $rp($s->payout_amount) }}</td>
                                <td>
                                    <span class="badge badge-light-{{ $s->status === 'paid' ? 'success' : 'warning' }}">{{ $s->status === 'paid' ? 'Dibayar' : 'Belum dibayar' }}</span>
                                    @if ($s->reference)<div class="text-muted fs-8">Ref {{ $s->reference }}</div>@endif
                                </td>
                                <td class="text-end"><button type="button" class="btn btn-sm btn-light-primary" data-hms-show="{{ route('platform.settlements.show', $s) }}" data-title="Setoran {{ $s->hotel?->name }}">Detail</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-8">Belum ada setoran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $settlements->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </x-hms.content>
@endsection
