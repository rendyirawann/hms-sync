@php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); $hotel = $settlement->hotel; @endphp

<div class="d-flex flex-wrap justify-content-between gap-3 mb-6">
    <div>
        <div class="fs-3 fw-bold">{{ $hotel->name }}</div>
        <div class="text-muted">Periode {{ $settlement->period_start->translatedFormat('d M Y') }} - {{ $settlement->period_end->translatedFormat('d M Y') }}</div>
    </div>
    <span class="badge badge-lg badge-light-{{ $settlement->status === 'paid' ? 'success' : 'warning' }} align-self-start">{{ $settlement->status === 'paid' ? 'Dibayar ' . $settlement->paid_at?->format('d/m/Y') : 'Belum dibayar' }}</span>
</div>

<table class="table table-row-dashed fs-7 gy-2 mb-6">
    <tbody>
        <tr><td>Pendapatan kamar (setelah diskon)</td><td class="text-end">{{ $rp($settlement->room_revenue) }}</td></tr>
        <tr><td>Total tagihan tamu</td><td class="text-end">{{ $rp($settlement->gross_amount) }}</td></tr>
        <tr><td>Diterima platform (Tripay/transfer)</td><td class="text-end">{{ $rp($settlement->collected_by_platform) }}</td></tr>
        <tr><td>Diterima hotel (tunai/EDC)</td><td class="text-end">{{ $rp($settlement->collected_by_hotel) }}</td></tr>
        <tr><td>Komisi platform</td><td class="text-end text-danger">- {{ $rp($settlement->commission_amount) }}</td></tr>
        <tr class="fw-bolder fs-6"><td>{{ $settlement->payout_amount < 0 ? 'Hotel membayar ke platform' : 'Platform membayar ke hotel' }}</td><td class="text-end">{{ $rp(abs($settlement->payout_amount)) }}</td></tr>
    </tbody>
</table>

<div class="text-muted fs-7 mb-6">
    Rekening hotel: {{ $hotel->payout_bank ?: '-' }} {{ $hotel->payout_account_number }} a.n. {{ $hotel->payout_account_name ?: '-' }}
</div>

<h5 class="mb-3">{{ $settlement->bookings->count() }} stay</h5>
<div class="table-responsive mb-6" style="max-height: 280px;">
    <table class="table table-row-dashed fs-8 gy-1">
        <thead><tr class="text-gray-500 fw-bold"><th>Kode</th><th>Check-out</th><th>Kamar</th><th class="text-end">Kamar - diskon</th><th class="text-end">Komisi</th></tr></thead>
        <tbody>
            @foreach ($settlement->bookings as $b)
                @php $base = (float) $b->room_total - (float) $b->discount; @endphp
                <tr>
                    <td>{{ $b->booking_code }}</td>
                    <td>{{ $b->check_out_date->format('d/m') }}</td>
                    <td>{{ $b->roomType?->name }}</td>
                    <td class="text-end">{{ $rp($base) }}</td>
                    <td class="text-end">{{ $rp($base * (float) ($b->commission_percent ?? $hotel->commission_percent) / 100) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($settlement->status !== 'paid')
    <form method="POST" action="{{ route('platform.settlements.paid', $settlement) }}" data-ajax class="d-flex flex-wrap gap-3 align-items-end">
        @csrf
        <div class="flex-grow-1">
            <label class="form-label">No. referensi transfer</label>
            <input type="text" name="reference" class="form-control form-control-solid">
        </div>
        <div class="flex-grow-1">
            <label class="form-label">Catatan</label>
            <input type="text" name="note" class="form-control form-control-solid">
        </div>
        <button type="submit" class="btn btn-success">Tandai sudah dibayar</button>
    </form>
@endif
