@php
    $reservation = $booking->reservation;
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $inHouse = $booking->is_checked_in;
    $holding = in_array($booking->status, \App\Models\Booking::HOLDING, true);
@endphp

<div class="d-flex flex-wrap justify-content-between gap-3 mb-6">
    <div>
        <div class="fs-3 fw-bold text-gray-900">
            {{ $booking->guest_display_name }}
            @if ($booking->source === 'walkin') <span class="badge badge-light-dark fs-8 align-middle">walk-in</span> @endif
        </div>
        <div class="text-muted">{{ $booking->guest_contact_email ?: '-' }} &middot; {{ $booking->guest_phone ?: '-' }}</div>
    </div>
    <div class="text-end">
        <span class="badge badge-lg badge-light-{{ $booking->status_badge }}">{{ $booking->status_label }}</span>
        @if ($inHouse) <span class="badge badge-lg badge-success">Menginap</span> @endif
        <div class="text-muted fs-7 mt-1">Dibuat {{ $booking->created_at->translatedFormat('d M Y H:i') }}</div>
        @if ($booking->payment_due_at)
            <div class="text-danger fs-7">Batas bayar {{ $booking->payment_due_at->translatedFormat('d M Y H:i') }}</div>
        @endif
    </div>
</div>

<div class="row g-5 mb-6">
    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-7">Tipe kamar</div><div class="fw-bold">{{ $booking->roomType->name }}</div></div>
    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-7">Check-in</div><div class="fw-bold">{{ $booking->check_in_date->translatedFormat('d M Y') }}</div></div>
    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-7">Check-out</div><div class="fw-bold">{{ $booking->check_out_date->translatedFormat('d M Y') }}</div></div>
    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-7">Lama / tamu</div><div class="fw-bold">{{ $booking->nights }} malam &middot; {{ $booking->guests }} tamu</div></div>
</div>

@if ($booking->notes)
    <div class="notice bg-light-primary rounded p-4 mb-6"><span class="fw-bold">Catatan tamu:</span> {{ $booking->notes }}</div>
@endif
@if ($booking->cancel_reason)
    <div class="notice bg-light-danger rounded p-4 mb-6"><span class="fw-bold">Keterangan:</span> {{ $booking->cancel_reason }}</div>
@endif

@if ($reservation)
    <div class="border border-dashed rounded p-5 mb-6 d-flex flex-wrap align-items-center justify-content-between gap-4">
        <div><div class="text-muted fs-7">Kamar</div><div class="fs-2 fw-bold">{{ $reservation->room->room_number }}</div></div>
        <div><div class="text-muted fs-7">Check-in aktual</div><div class="fw-bold">{{ $reservation->actual_check_in?->translatedFormat('d M Y H:i') }}</div></div>
        @if ($reservation->status === 'Active')
            <div><div class="text-muted fs-7">Passcode pintu</div><div class="fs-2x fw-bolder text-primary font-monospace">{{ $reservation->door_passcode }}</div></div>
        @else
            <div><div class="text-muted fs-7">Check-out aktual</div><div class="fw-bold">{{ $reservation->actual_check_out?->translatedFormat('d M Y H:i') }}</div></div>
        @endif
    </div>
    @if ($reservation->moves->isNotEmpty())
        <div class="text-muted fs-7 mb-6">
            Riwayat pindah kamar:
            @foreach ($reservation->moves as $move)
                {{ $move->fromRoom->room_number }} &rarr; {{ $move->toRoom->room_number }} ({{ $move->created_at->format('d/m H:i') }}{{ $move->reason ? ', ' . $move->reason : '' }})@if (! $loop->last); @endif
            @endforeach
        </div>
    @endif
@endif

{{-- Folio --}}
<h5 class="mb-3">Tagihan</h5>
<table class="table table-row-dashed fs-7 gy-2 mb-2">
    <tbody>
        <tr><td>Kamar {{ $booking->nights }} malam</td><td class="text-end">{{ $rp($booking->room_total) }}</td><td class="w-25px"></td></tr>
        @if ((float) $booking->discount > 0)
            <tr><td>Diskon {{ $booking->promoCode?->code }}</td><td class="text-end text-success">- {{ $rp($booking->discount) }}</td><td></td></tr>
        @endif
        <tr><td>Service charge</td><td class="text-end">{{ $rp($booking->service_charge) }}</td><td></td></tr>
        <tr><td>Pajak</td><td class="text-end">{{ $rp($booking->tax) }}</td><td></td></tr>
        @foreach ($booking->charges as $charge)
            <tr>
                <td>{{ \App\Models\BookingCharge::CATEGORIES[$charge->category] ?? $charge->category }}: {{ $charge->description }} @if ($charge->quantity > 1) ({{ $charge->quantity }}x) @endif</td>
                <td class="text-end">{{ $rp($charge->amount) }}</td>
                <td class="text-end">
                    @if ($inHouse && $charge->category !== 'extend')
                        <form method="POST" action="{{ route('hms.bookings.charges.destroy', [$booking, $charge]) }}" data-ajax data-ajax-confirm="Hapus tagihan ini?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-icon btn-sm btn-light-danger w-20px h-20px" data-loading-text=""><i class="ki-outline ki-cross fs-6"></i></button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        <tr class="fw-bold border-top"><td>Total</td><td class="text-end">{{ $rp($booking->grand_total) }}</td><td></td></tr>
        <tr><td>Sudah dibayar</td><td class="text-end text-success">{{ $rp($booking->amount_paid) }}</td><td></td></tr>
        <tr class="fw-bolder fs-6"><td>Sisa tagihan</td><td class="text-end {{ $booking->balance_due > 0 ? 'text-danger' : 'text-success' }}">{{ $rp($booking->balance_due) }}</td><td></td></tr>
    </tbody>
</table>

{{-- Payments --}}
@if ($booking->payments->isNotEmpty())
    <h5 class="mt-6 mb-3">Pembayaran</h5>
    @foreach ($booking->payments as $payment)
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border rounded p-3 mb-2">
            <div>
                <span class="fw-bold">{{ $payment->method_label }}</span> &middot; {{ $rp($payment->amount) }}
                <span class="badge badge-light-{{ $payment->status_badge }} ms-1">{{ $payment->status_label }}</span>
                <div class="text-muted fs-8">
                    {{ $payment->created_at->format('d/m/Y H:i') }}
                    @if ($payment->reference) &middot; Ref {{ $payment->reference }} @endif
                    @if ($payment->verifier) &middot; oleh {{ $payment->verifier->name }} @endif
                    @if ($payment->note) &middot; {{ $payment->note }} @endif
                </div>
            </div>
            <div class="d-flex gap-2">
                @if ($payment->proof_path)
                    <a href="{{ route('hms.bookings.payments.proof', $payment) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light">Lihat bukti</a>
                @endif
                @if ($payment->method === 'transfer' && $payment->status === 'pending')
                    <form method="POST" action="{{ route('hms.bookings.payments.verify', $payment) }}" data-ajax data-ajax-confirm="Nominal di bukti transfer sudah sesuai?" data-confirm-icon="question" data-confirm-ok="Ya, terima">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">Terima</button>
                    </form>
                    <form method="POST" action="{{ route('hms.bookings.payments.reject', $payment) }}" data-ajax data-ajax-confirm="Tolak bukti transfer ini?">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-light-danger">Tolak</button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
@endif

<div class="separator my-6"></div>

<div class="d-flex flex-wrap gap-3 justify-content-end">
    <a href="{{ route('hms.bookings.invoice', $booking) }}" class="btn btn-light"><i class="ki-outline ki-document fs-3"></i> Invoice PDF</a>

    @if (in_array($booking->status, ['Pending', 'Paid'], true))
        <form method="POST" action="{{ route('hms.bookings.confirm', $booking) }}" data-ajax>
            @csrf
            <button type="submit" class="btn btn-primary"><i class="ki-outline ki-check fs-3"></i> Konfirmasi</button>
        </form>
    @endif

    @if ($holding && ! $reservation)
        <form method="POST" action="{{ route('hms.bookings.cancel', $booking) }}" data-ajax
            data-ajax-confirm="Booking akan dibatalkan dan kamar dilepas kembali." data-confirm-title="Batalkan booking?" data-confirm-ok="Ya, batalkan">
            @csrf
            <input type="hidden" name="cancel_reason" value="Dibatalkan oleh hotel">
            <button type="submit" class="btn btn-light-danger"><i class="ki-outline ki-cross-circle fs-3"></i> Batalkan</button>
        </form>
    @endif

    @if ($inHouse)
        <form method="POST" action="{{ route('hms.bookings.passcode', $booking) }}" data-ajax data-ajax-confirm="Passcode lama tidak akan berlaku lagi." data-confirm-icon="question">
            @csrf
            <button type="submit" class="btn btn-light"><i class="ki-outline ki-arrows-circle fs-3"></i> Passcode Baru</button>
        </form>
        <form method="POST" action="{{ route('hms.bookings.check-out', $booking) }}" data-ajax
            data-ajax-confirm="Tamu check-out dari kamar {{ $reservation->room->room_number }}?" data-confirm-icon="question" data-confirm-ok="Ya, check-out">
            @csrf
            <button type="submit" class="btn btn-success"><i class="ki-outline ki-exit-right fs-3"></i> Check-out</button>
        </form>
    @endif
</div>

{{-- Record a payment at the desk --}}
@if (($holding || $inHouse) && $booking->balance_due > 0)
    <div class="separator my-6"></div>
    <h5 class="mb-4">Catat pembayaran di front desk</h5>
    <form method="POST" action="{{ route('hms.bookings.paid', $booking) }}" data-ajax class="row g-3 align-items-end">
        @csrf
        <div class="col-md-4">
            <label class="form-label">Metode</label>
            <select name="method" class="form-select form-select-solid">
                <option value="cash">Tunai / EDC</option>
                <option value="transfer">Transfer (sudah dicek)</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Nominal</label>
            <input type="number" name="amount" min="1" step="1" value="{{ (int) round($booking->balance_due) }}" class="form-control form-control-solid">
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-info w-100"><i class="ki-outline ki-wallet fs-3"></i> Simpan pembayaran</button>
        </div>
    </form>
@endif

{{-- Check-in --}}
@if (in_array($booking->status, ['Paid', 'Confirmed'], true) && ! $reservation && $booking->check_in_date->lte(today()) && $booking->check_out_date->gt(today()))
    <div class="separator my-6"></div>
    <h5 class="mb-4">Check-in</h5>
    @if ($rooms->isEmpty())
        <div class="alert alert-warning mb-0">Tidak ada kamar {{ $booking->roomType->name }} yang siap (status Tersedia). Cek Housekeeping.</div>
    @else
        <form method="POST" action="{{ route('hms.bookings.check-in', $booking) }}" data-ajax class="d-flex flex-wrap gap-3 align-items-end">
            @csrf
            <div class="flex-grow-1">
                <label class="form-label">Pilih kamar</label>
                <select name="room_id" class="form-select form-select-solid">
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}">Kamar {{ $room->room_number }}{{ $room->floor ? ' - Lantai ' . $room->floor : '' }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary" data-loading-text="Memproses check-in...">
                <i class="ki-outline ki-key fs-3"></i> Check-in &amp; Buat Passcode
            </button>
        </form>
    @endif
@elseif (in_array($booking->status, ['Paid', 'Confirmed'], true) && ! $reservation && $booking->check_in_date->gt(today()))
    <div class="text-muted text-end fs-7 mt-4">Check-in bisa dilakukan mulai {{ $booking->check_in_date->translatedFormat('d M Y') }}.</div>
@endif

{{-- In-house operations --}}
@if ($inHouse)
    <div class="separator my-6"></div>
    <div class="row g-6">
        <div class="col-lg-6">
            <h5 class="mb-4">Tambah tagihan (folio)</h5>
            <form method="POST" action="{{ route('hms.bookings.charges.store', $booking) }}" data-ajax class="d-flex flex-column gap-3">
                @csrf
                <div class="d-flex gap-3">
                    <select name="category" class="form-select form-select-solid">
                        @foreach (\App\Models\BookingCharge::CATEGORIES as $key => $label)
                            @continue($key === 'extend')
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="quantity" value="1" min="1" class="form-control form-control-solid w-80px" title="Jumlah">
                </div>
                <input type="text" name="description" class="form-control form-control-solid" placeholder="Keterangan, mis. Air mineral">
                <div class="d-flex gap-3">
                    <input type="number" name="unit_price" min="0" step="500" class="form-control form-control-solid" placeholder="Harga satuan (Rp)">
                    <button type="submit" class="btn btn-light-primary text-nowrap">Tambah</button>
                </div>
            </form>
        </div>
        <div class="col-lg-6">
            <h5 class="mb-4">Perpanjang menginap</h5>
            <form method="POST" action="{{ route('hms.bookings.extend', $booking) }}" data-ajax class="d-flex gap-3 mb-6"
                data-ajax-confirm="Tambah malam menginap? Harga mengikuti tarif berlaku dan masuk ke tagihan." data-confirm-icon="question">
                @csrf
                <input type="date" name="check_out" min="{{ $booking->check_out_date->copy()->addDay()->toDateString() }}" value="{{ $booking->check_out_date->copy()->addDay()->toDateString() }}" class="form-control form-control-solid">
                <button type="submit" class="btn btn-light-primary text-nowrap">Perpanjang</button>
            </form>

            <h5 class="mb-4">Pindah kamar</h5>
            @if ($moveTargets->isEmpty())
                <div class="text-muted fs-7">Tidak ada kamar kosong yang siap.</div>
            @else
                <form method="POST" action="{{ route('hms.bookings.move', $booking) }}" data-ajax class="d-flex flex-column gap-3"
                    data-ajax-confirm="Pindahkan tamu? Kamar lama masuk antrean housekeeping." data-confirm-icon="question">
                    @csrf
                    <select name="room_id" class="form-select form-select-solid">
                        @foreach ($moveTargets as $room)
                            <option value="{{ $room->id }}">{{ $room->room_number }} &middot; {{ $room->roomType->name }}</option>
                        @endforeach
                    </select>
                    <div class="d-flex gap-3">
                        <input type="text" name="reason" class="form-control form-control-solid" placeholder="Alasan (opsional)">
                        <button type="submit" class="btn btn-light-warning text-nowrap">Pindahkan</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endif
