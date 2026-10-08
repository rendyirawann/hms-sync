@extends('backend.layout.app')
@section('title', 'Reservasi')

@section('content')
    <x-hms.toolbar title="Reservasi" section="Front Desk">
        <a href="{{ route('hms.bookings.calendar') }}" class="btn btn-sm btn-light"><i class="ki-outline ki-calendar-8 fs-4"></i> Kalender</a>
        <button type="button" class="btn btn-sm btn-primary" data-hms-drawer="#drawer-walkin" data-title="Booking Walk-in">
            <i class="ki-outline ki-plus fs-3"></i> Walk-in
        </button>
    </x-hms.toolbar>

    <span hidden data-live-reload="booking"></span>
    <x-hms.content>
        <div class="row g-5 mb-5">
            @foreach ([
                ['Menunggu pembayaran', $counts['Pending'], 'warning', ['status' => 'Pending']],
                ['Bukti transfer perlu dicek', $counts['verify'], 'danger', ['needs_verification' => 1]],
                ['Kedatangan hari ini', $counts['arrivals'], 'primary', ['date' => today()->toDateString()]],
                ['Tamu menginap', $counts['InHouse'], 'success', ['status' => 'InHouse']],
            ] as [$label, $value, $color, $query])
                <div class="col-6 col-lg-3">
                    <a href="{{ route('hms.bookings.index', $query) }}" class="card card-flush h-100">
                        <div class="card-body py-5">
                            <div class="fs-2hx fw-bold text-{{ $color }}">{{ $value }}</div>
                            <div class="text-gray-600 fw-semibold">{{ $label }}</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="card card-flush">
            <div class="card-header py-5">
                <form method="GET" class="card-title d-flex flex-wrap gap-3" data-no-spinner>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-solid w-225px" placeholder="Kode / nama / email tamu">
                    <select name="status" class="form-select form-select-solid w-175px" onchange="this.form.submit()">
                        <option value="">Semua status</option>
                        <option value="InHouse" @selected(request('status') === 'InHouse')>Sedang menginap</option>
                        @foreach (\App\Models\Booking::STATUSES as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="date" value="{{ request('date') }}" class="form-control form-control-solid w-175px" title="Tanggal check-in" onchange="this.form.submit()">
                    @if (request()->hasAny(['q', 'status', 'date', 'needs_verification']))
                        <a href="{{ route('hms.bookings.index') }}" class="btn btn-light">Reset</a>
                    @endif
                </form>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase">
                                <th>Kode</th>
                                <th>Tamu</th>
                                <th>Tipe kamar</th>
                                <th>Menginap</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @forelse ($bookings as $booking)
                                <tr>
                                    <td class="text-gray-900 fw-bold">{{ $booking->booking_code }}</td>
                                    <td>
                                        {{ $booking->guest_display_name }}
                                        @if ($booking->source === 'walkin')
                                            <span class="badge badge-light-dark ms-1">walk-in</span>
                                        @endif
                                        <div class="text-muted fs-7">{{ $booking->guest_phone ?: $booking->guest_contact_email }}</div>
                                    </td>
                                    <td>{{ $booking->roomType->name }}
                                        @if ($booking->reservation)
                                            <div class="text-muted fs-7">Kamar {{ $booking->reservation->room->room_number }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $booking->check_in_date->format('d/m/Y') }} - {{ $booking->check_out_date->format('d/m/Y') }}
                                        <div class="text-muted fs-7">{{ $booking->nights }} malam &middot; {{ $booking->guests }} tamu</div>
                                    </td>
                                    <td>Rp {{ number_format($booking->total_price, 0, ',', '.') }}</td>
                                    <td>
                                        <span class="badge badge-light-{{ $booking->status_badge }}">{{ $booking->status_label }}</span>
                                        @if ($booking->is_checked_in)
                                            <span class="badge badge-success">Menginap</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-light-primary"
                                            data-hms-show="{{ route('hms.bookings.show', $booking) }}" data-title="Reservasi {{ $booking->booking_code }}">
                                            <i class="ki-outline ki-eye fs-4"></i> Kelola
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-10">Tidak ada reservasi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $bookings->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </x-hms.content>

    <x-hms.drawer id="drawer-walkin" title="Booking Walk-in" :action="route('hms.bookings.store')" width="520px">
        <div class="notice bg-light-primary rounded p-4 fs-7">
            Untuk tamu yang datang langsung. Booking otomatis terkonfirmasi; pembayaran &amp; check-in dilanjutkan dari tombol <b>Kelola</b>.
        </div>
        <div>
            <label class="form-label required">Tipe kamar</label>
            <select name="room_type_id" class="form-select form-select-solid">
                <option value="">Pilih tipe</option>
                @foreach ($roomTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }} (maks. {{ $type->capacity }} tamu)</option>
                @endforeach
            </select>
        </div>
        <div class="row g-5">
            <div class="col-6">
                <label class="form-label required">Check-in</label>
                <input type="date" name="check_in" value="{{ today()->toDateString() }}" class="form-control form-control-solid">
            </div>
            <div class="col-6">
                <label class="form-label required">Check-out</label>
                <input type="date" name="check_out" value="{{ today()->addDay()->toDateString() }}" class="form-control form-control-solid">
            </div>
        </div>
        <div>
            <label class="form-label required">Nama tamu</label>
            <input type="text" name="guest_name" class="form-control form-control-solid">
        </div>
        <div class="row g-5">
            <div class="col-6">
                <label class="form-label required">Telepon</label>
                <input type="text" name="guest_phone" class="form-control form-control-solid">
            </div>
            <div class="col-6">
                <label class="form-label">Email (opsional)</label>
                <input type="email" name="guest_email" class="form-control form-control-solid">
            </div>
        </div>
        <div class="row g-5">
            <div class="col-6">
                <label class="form-label required">Jumlah tamu</label>
                <input type="number" name="guests" value="1" min="1" max="20" class="form-control form-control-solid">
            </div>
            <div class="col-6">
                <label class="form-label">Kode promo</label>
                <input type="text" name="promo_code" class="form-control form-control-solid text-uppercase">
            </div>
        </div>
        <div>
            <label class="form-label">Catatan</label>
            <textarea name="notes" rows="2" class="form-control form-control-solid"></textarea>
        </div>
    </x-hms.drawer>
@endsection
