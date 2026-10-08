@php $reservation = $booking->reservation; @endphp

<div class="d-flex flex-wrap justify-content-between gap-3 mb-6">
    <div>
        <div class="fs-3 fw-bold text-gray-900">{{ $booking->user->name }}</div>
        <div class="text-muted">{{ $booking->user->email }} &middot; {{ $booking->guest_phone ?: '-' }}</div>
    </div>
    <div class="text-end">
        <span class="badge badge-lg badge-light-{{ $booking->status_badge }}">{{ $booking->status }}</span>
        <div class="text-muted fs-7 mt-1">Dibuat {{ $booking->created_at->translatedFormat('d M Y H:i') }}</div>
    </div>
</div>

<div class="row g-5 mb-6">
    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-7">Tipe kamar</div><div class="fw-bold">{{ $booking->roomType->name }}</div></div>
    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-7">Check-in</div><div class="fw-bold">{{ $booking->check_in_date->translatedFormat('d M Y') }}</div></div>
    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-7">Check-out</div><div class="fw-bold">{{ $booking->check_out_date->translatedFormat('d M Y') }}</div></div>
    <div class="col-sm-6 col-lg-3"><div class="text-muted fs-7">Total ({{ $booking->nights }} malam, {{ $booking->guests }} tamu)</div><div class="fw-bold">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</div></div>
</div>

@if ($booking->notes)
    <div class="notice bg-light-primary rounded p-4 mb-6"><span class="fw-bold">Catatan tamu:</span> {{ $booking->notes }}</div>
@endif
@if ($booking->cancel_reason)
    <div class="notice bg-light-danger rounded p-4 mb-6"><span class="fw-bold">Alasan batal:</span> {{ $booking->cancel_reason }}</div>
@endif

@if ($reservation)
    <div class="border border-dashed rounded p-5 mb-6 d-flex flex-wrap align-items-center justify-content-between gap-4">
        <div>
            <div class="text-muted fs-7">Kamar</div>
            <div class="fs-2 fw-bold">{{ $reservation->room->room_number }}</div>
        </div>
        <div>
            <div class="text-muted fs-7">Check-in aktual</div>
            <div class="fw-bold">{{ $reservation->actual_check_in?->translatedFormat('d M Y H:i') }}</div>
        </div>
        @if ($reservation->status === 'Active')
            <div>
                <div class="text-muted fs-7">Passcode pintu</div>
                <div class="fs-2x fw-bolder text-primary font-monospace ls-2">{{ $reservation->door_passcode }}</div>
            </div>
        @else
            <div>
                <div class="text-muted fs-7">Check-out aktual</div>
                <div class="fw-bold">{{ $reservation->actual_check_out?->translatedFormat('d M Y H:i') }}</div>
            </div>
        @endif
    </div>
@endif

<div class="separator mb-6"></div>

<div class="d-flex flex-wrap gap-3 justify-content-end">
    @if ($booking->status === 'Pending')
        <form method="POST" action="{{ route('hms.bookings.paid', $booking) }}" data-ajax>
            @csrf
            <button type="submit" class="btn btn-light-info"><i class="ki-outline ki-wallet fs-3"></i> Tandai Lunas</button>
        </form>
    @endif

    @if (in_array($booking->status, ['Pending', 'Paid'], true))
        <form method="POST" action="{{ route('hms.bookings.confirm', $booking) }}" data-ajax>
            @csrf
            <button type="submit" class="btn btn-primary"><i class="ki-outline ki-check fs-3"></i> Konfirmasi</button>
        </form>
    @endif

    @if (in_array($booking->status, \App\Models\Booking::HOLDING, true) && ! $reservation)
        <form method="POST" action="{{ route('hms.bookings.cancel', $booking) }}" data-ajax
            data-ajax-confirm="Booking akan dibatalkan dan kamar dilepas kembali." data-confirm-title="Batalkan booking?" data-confirm-ok="Ya, batalkan">
            @csrf
            <input type="hidden" name="cancel_reason" value="Dibatalkan oleh hotel">
            <button type="submit" class="btn btn-light-danger"><i class="ki-outline ki-cross-circle fs-3"></i> Batalkan</button>
        </form>
    @endif

    @if ($reservation?->status === 'Active')
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

@if ($rooms->isNotEmpty() || (in_array($booking->status, ['Paid', 'Confirmed'], true) && ! $reservation && $booking->check_in_date->lte(today()) && $booking->check_out_date->gt(today())))
    <div class="separator my-6"></div>
    <h5 class="mb-4">Check-in</h5>
    @if ($rooms->isEmpty())
        <div class="alert alert-warning mb-0">Tidak ada kamar {{ $booking->roomType->name }} yang siap (status Tersedia). Cek halaman Kamar.</div>
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
