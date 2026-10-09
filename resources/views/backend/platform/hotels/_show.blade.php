@php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp

<div class="d-flex flex-wrap justify-content-between gap-3 mb-6">
    <div>
        <div class="fs-3 fw-bold text-gray-900">{{ $hotel->name }}</div>
        <div class="text-muted">{{ $hotel->address }}{{ $hotel->city ? ', ' . $hotel->city : '' }}</div>
        <div class="text-muted fs-7">Telp {{ $hotel->phone ?: '-' }} &middot; {{ $hotel->email ?: '-' }} &middot; terdaftar {{ $hotel->created_at->translatedFormat('d M Y') }}</div>
    </div>
    <span class="badge badge-lg badge-light-{{ $hotel->approval_badge }} align-self-start">{{ $hotel->approval_label }}</span>
</div>

@if ($hotel->rejection_reason)
    <div class="notice bg-light-danger rounded p-4 mb-6">Alasan penolakan: {{ $hotel->rejection_reason }}</div>
@endif

<div class="row g-5 mb-6">
    <div class="col-md-4"><div class="text-muted fs-7">Pemilik</div><div class="fw-bold">{{ $hotel->owner?->name ?? '-' }}</div><div class="fs-7">{{ $hotel->owner?->email }}</div></div>
    <div class="col-md-4"><div class="text-muted fs-7">Tipe kamar</div><div class="fw-bold">{{ $hotel->roomTypes->count() }} tipe &middot; {{ $hotel->roomTypes->sum('rooms_count') }} kamar</div></div>
    <div class="col-md-4"><div class="text-muted fs-7">Staf</div><div class="fw-bold">{{ $hotel->staff->count() }} akun</div></div>
</div>

<div class="d-flex flex-wrap gap-3 mb-6">
    @if ($hotel->approval_status !== 'approved')
        <form method="POST" action="{{ route('platform.hotels.approve', $hotel) }}" data-ajax data-ajax-confirm="Setujui {{ $hotel->name }}? Hotel langsung tampil di website." data-confirm-icon="question" data-confirm-ok="Ya, setujui">
            @csrf
            <button type="submit" class="btn btn-success"><i class="ki-outline ki-check fs-3"></i> Setujui</button>
        </form>
    @endif
    @if ($hotel->approval_status === 'pending')
        <form method="POST" action="{{ route('platform.hotels.reject', $hotel) }}" data-ajax class="d-flex gap-2">
            @csrf
            <input type="text" name="reason" class="form-control form-control-solid w-250px" placeholder="Alasan penolakan">
            <button type="submit" class="btn btn-light-danger">Tolak</button>
        </form>
    @endif
    @if ($hotel->approval_status === 'approved')
        <form method="POST" action="{{ route('platform.hotels.toggle', $hotel) }}" data-ajax
            data-ajax-confirm="{{ $hotel->status ? 'Nonaktifkan hotel? Hotel disembunyikan dari website dan tidak menerima booking baru.' : 'Aktifkan kembali hotel ini?' }}">
            @csrf
            <button type="submit" class="btn {{ $hotel->status ? 'btn-light-danger' : 'btn-light-success' }}">{{ $hotel->status ? 'Nonaktifkan' : 'Aktifkan' }}</button>
        </form>
    @endif
    <a href="{{ route('hotels.show', $hotel) }}" target="_blank" rel="noopener" class="btn btn-light">Lihat halaman publik</a>
</div>

<div class="separator mb-6"></div>
<h5 class="mb-4">Komisi &amp; rekening setoran</h5>
<form method="POST" action="{{ route('platform.hotels.update', $hotel) }}" data-ajax class="row g-4">
    @csrf
    @method('PUT')
    <div class="col-md-6"><label class="form-label">Nama hotel</label><input type="text" name="name" value="{{ $hotel->name }}" class="form-control form-control-solid"></div>
    <div class="col-md-3"><label class="form-label">Kota</label><input type="text" name="city" value="{{ $hotel->city }}" class="form-control form-control-solid"></div>
    <div class="col-md-3"><label class="form-label">Komisi (%)</label><input type="number" step="0.5" min="0" max="100" name="commission_percent" value="{{ (float) $hotel->commission_percent }}" class="form-control form-control-solid"></div>
    <div class="col-md-12"><label class="form-label">Alamat halaman (slug)</label><input type="text" name="slug" value="{{ $hotel->slug }}" class="form-control form-control-solid"></div>
    <div class="col-md-4"><label class="form-label">Bank</label><input type="text" name="payout_bank" value="{{ $hotel->payout_bank }}" class="form-control form-control-solid"></div>
    <div class="col-md-4"><label class="form-label">No. rekening</label><input type="text" name="payout_account_number" value="{{ $hotel->payout_account_number }}" class="form-control form-control-solid"></div>
    <div class="col-md-4"><label class="form-label">Atas nama</label><input type="text" name="payout_account_name" value="{{ $hotel->payout_account_name }}" class="form-control form-control-solid"></div>
    <div class="col-12 text-end"><button type="submit" class="btn btn-primary">Simpan</button></div>
</form>

@if ($settlements->isNotEmpty())
    <div class="separator my-6"></div>
    <h5 class="mb-4">Setoran terakhir</h5>
    <table class="table table-row-dashed fs-7 gy-2">
        <tbody>
            @foreach ($settlements as $s)
                <tr>
                    <td>{{ $s->period_start->format('d/m/Y') }} - {{ $s->period_end->format('d/m/Y') }}</td>
                    <td class="text-end">{{ $rp($s->payout_amount) }}</td>
                    <td class="text-end"><span class="badge badge-light-{{ $s->status === 'paid' ? 'success' : 'warning' }}">{{ $s->status === 'paid' ? 'Dibayar' : 'Belum dibayar' }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
