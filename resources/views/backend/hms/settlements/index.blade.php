@extends('backend.layout.app')
@section('title', 'Setoran dari Platform')

@php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp

@section('content')
    <x-hms.toolbar title="Setoran dari Platform" section="Laporan" />

    <x-hms.content>
        <div class="card card-flush mb-6">
            <div class="card-body">
                <p class="text-gray-700 mb-4">
                    Pembayaran online tamu (Tripay &amp; transfer) diterima platform, lalu disetor ke rekening hotel setelah dipotong komisi
                    <b>{{ (float) $hotel->commission_percent }}%</b> dari pendapatan kamar. Tunai yang diterima di front desk tetap di hotel dan komisinya diperhitungkan di setoran.
                </p>
                <div class="text-muted fs-7">
                    Rekening setoran: {{ $hotel->payout_bank ?: '-' }} {{ $hotel->payout_account_number }} a.n. {{ $hotel->payout_account_name ?: '-' }}
                    &middot; ubah di <a href="{{ route('hms.hotel.edit') }}">Profil Hotel</a>
                </div>
            </div>
        </div>

        <div class="card card-flush mb-6">
            <div class="card-header">
                <h3 class="card-title">Perkiraan periode {{ $from->format('d/m/Y') }} - {{ $to->format('d/m/Y') }}</h3>
                <div class="card-toolbar">
                    <form method="GET" class="d-flex gap-2" data-no-spinner>
                        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm form-control-solid">
                        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm form-control-solid">
                        <button type="submit" class="btn btn-sm btn-light">Hitung</button>
                    </form>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="row g-5">
                    @foreach ([
                        ['Stay selesai belum disetor', $preview['bookings_count'], false],
                        ['Diterima platform', $preview['collected_by_platform'], true],
                        ['Diterima hotel (tunai)', $preview['collected_by_hotel'], true],
                        ['Komisi', $preview['commission_amount'], true],
                        ['Perkiraan setoran', $preview['payout_amount'], true],
                    ] as [$label, $value, $money])
                        <div class="col-6 col-lg">
                            <div class="text-gray-600 fs-7">{{ $label }}</div>
                            <div class="fs-4 fw-bold">{{ $money ? $rp($value) : $value }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card card-flush">
            <div class="card-header"><h3 class="card-title">Riwayat setoran</h3></div>
            <div class="card-body pt-0 table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-4">
                    <thead><tr class="text-gray-500 fw-bold fs-7 text-uppercase"><th>Periode</th><th class="text-end">Stay</th><th class="text-end">Komisi</th><th class="text-end">Setoran</th><th>Status</th></tr></thead>
                    <tbody class="fw-semibold text-gray-700">
                        @forelse ($settlements as $s)
                            <tr>
                                <td>{{ $s->period_start->format('d/m/Y') }} - {{ $s->period_end->format('d/m/Y') }}</td>
                                <td class="text-end">{{ $s->bookings_count }}</td>
                                <td class="text-end">{{ $rp($s->commission_amount) }}</td>
                                <td class="text-end fw-bold">{{ $rp($s->payout_amount) }}</td>
                                <td>
                                    <span class="badge badge-light-{{ $s->status === 'paid' ? 'success' : 'warning' }}">{{ $s->status === 'paid' ? 'Dibayar ' . $s->paid_at?->format('d/m/Y') : 'Diproses' }}</span>
                                    @if ($s->reference)<div class="text-muted fs-8">Ref {{ $s->reference }}</div>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-8">Belum ada setoran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $settlements->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </x-hms.content>
@endsection
