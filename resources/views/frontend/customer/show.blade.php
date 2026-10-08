@extends('frontend.layout')
@section('title', 'Reservasi ' . $booking->booking_code . ' - ' . $hotel->name)

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $res = $booking->reservation;
    $pendingTransfer = $booking->payments->first(fn ($p) => $p->method === 'transfer' && $p->status === 'pending');
    $pendingTripay = $booking->payments->first(fn ($p) => $p->method === 'tripay' && $p->status === 'pending' && $p->expires_at?->isFuture());
    $statusStyle = [
        'Pending' => 'bg-yellow-50 text-yellow-700', 'Paid' => 'bg-sky-50 text-sky-700', 'Confirmed' => 'bg-blue-50 text-blue-700',
        'Completed' => 'bg-green-50 text-green-700', 'Cancelled' => 'bg-red-50 text-red-600', 'Expired' => 'bg-slate-100 text-slate-600',
        'NoShow' => 'bg-slate-100 text-slate-600',
    ];
@endphp

@section('content')
    <a href="{{ route('customer.bookings.index') }}" class="inline-block text-sm font-bold text-blue-600 mb-6">&larr; Reservasi saya</a>

    <div class="grid lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 flex flex-col gap-6">
            <section class="bg-white rounded-3xl border border-slate-100 shadow-lg shadow-slate-200/30 p-8">
                <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
                    <div>
                        <p class="text-xs font-bold text-slate-400">Kode reservasi</p>
                        <h1 class="text-2xl font-extrabold text-slate-800 tracking-wide">{{ $booking->booking_code }}</h1>
                    </div>
                    <span class="text-xs font-bold px-3 py-1.5 rounded-full {{ $statusStyle[$booking->status] ?? 'bg-slate-100' }}">
                        {{ $res?->status === 'Active' ? 'Sedang menginap' : $booking->status_label }}
                    </span>
                </div>

                @if ($res?->status === 'Active')
                    <div class="rounded-2xl bg-blue-600 text-white p-6 mb-6 flex flex-wrap gap-8">
                        <div><p class="text-xs font-bold text-blue-200 uppercase">Kamar</p><p class="text-3xl font-black">{{ $res->room->room_number }}</p></div>
                        <div><p class="text-xs font-bold text-blue-200 uppercase">Kode pintu</p><p class="text-3xl font-black tracking-[0.3em]">{{ $res->door_passcode }}</p></div>
                        <p class="text-xs text-blue-100 basis-full">Berlaku sampai check-out {{ $booking->check_out_date->translatedFormat('d M Y') }} pukul {{ substr($hotel->check_out_time, 0, 5) }}. Jangan bagikan kode ini.</p>
                    </div>
                @endif

                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-slate-400 font-bold">Tipe kamar</dt><dd class="font-bold text-slate-800">{{ $booking->roomType->name }}</dd></div>
                    <div><dt class="text-slate-400 font-bold">Tamu</dt><dd class="font-bold text-slate-800">{{ $booking->guests }} orang</dd></div>
                    <div><dt class="text-slate-400 font-bold">Check-in</dt><dd class="font-bold text-slate-800">{{ $booking->check_in_date->translatedFormat('l, d M Y') }} &middot; mulai {{ substr($hotel->check_in_time, 0, 5) }}</dd></div>
                    <div><dt class="text-slate-400 font-bold">Check-out</dt><dd class="font-bold text-slate-800">{{ $booking->check_out_date->translatedFormat('l, d M Y') }} &middot; s/d {{ substr($hotel->check_out_time, 0, 5) }}</dd></div>
                </dl>
                @if ($booking->cancel_reason)
                    <p class="mt-4 text-sm text-red-500">{{ $booking->cancel_reason }}</p>
                @endif
            </section>

            {{-- Payment --}}
            @if ($booking->status === 'Pending')
                <section class="bg-white rounded-3xl border border-slate-100 shadow-lg shadow-slate-200/30 p-8">
                    <h2 class="text-lg font-extrabold text-slate-800 mb-1">Pembayaran</h2>
                    @if ($booking->payment_due_at)
                        <p class="text-sm text-red-500 font-bold mb-5">Bayar sebelum {{ $booking->payment_due_at->translatedFormat('d M Y, H:i') }} atau reservasi dibatalkan otomatis.</p>
                    @endif

                    @if ($pendingTransfer)
                        <div class="rounded-2xl bg-yellow-50 border border-yellow-200 p-4 text-sm text-yellow-800 font-bold">
                            Bukti transfer sudah kami terima dan sedang diperiksa. Kami akan mengabari lewat email.
                        </div>
                    @else
                        @if ($pendingTripay)
                            <div class="rounded-2xl bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800 mb-5">
                                <p class="font-bold">Tagihan {{ $pendingTripay->method_label }} menunggu pembayaran.</p>
                                @if ($pendingTripay->payload['pay_code'] ?? null)
                                    <p class="mt-1">Kode bayar / VA: <span class="font-black tracking-wider">{{ $pendingTripay->payload['pay_code'] }}</span></p>
                                @endif
                                @if ($pendingTripay->checkout_url)
                                    <a href="{{ $pendingTripay->checkout_url }}" target="_blank" rel="noopener" class="inline-block mt-3 bg-blue-600 text-white px-5 py-2 rounded-xl font-black">Buka halaman pembayaran</a>
                                @endif
                                <p class="text-xs mt-2">Berlaku sampai {{ $pendingTripay->expires_at->translatedFormat('d M Y H:i') }}. Status otomatis berubah setelah pembayaran berhasil.</p>
                            </div>
                        @endif

                        @if ($tripayEnabled && $channels)
                            <form method="POST" action="{{ route('customer.bookings.pay', $booking) }}" class="mb-6"
                                onsubmit="var b=this.querySelector('[type=submit]'); if(b.disabled){return false;} b.disabled=true; b.textContent='Membuat tagihan...';">
                                @csrf
                                <p class="text-sm font-bold text-slate-600 mb-3">{{ $pendingTripay ? 'Atau ganti metode:' : 'Pilih metode pembayaran:' }}</p>
                                <div class="grid sm:grid-cols-2 gap-3 mb-4">
                                    @foreach ($channels as $channel)
                                        <label class="flex items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                                            <input type="radio" name="channel" value="{{ $channel['code'] }}" required class="accent-blue-600">
                                            @if ($channel['icon']) <img src="{{ $channel['icon'] }}" alt="" class="h-6 w-auto" loading="lazy"> @endif
                                            <span class="text-sm font-bold text-slate-700">{{ $channel['name'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <button type="submit" class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-8 py-3 rounded-2xl font-extrabold">Bayar {{ $rp($booking->balance_due) }}</button>
                            </form>
                        @endif

                        @if ($hotel->bank_accounts)
                            <details class="rounded-2xl border border-slate-200 p-4" @if (! $tripayEnabled || ! $channels) open @endif>
                                <summary class="font-bold text-slate-700 cursor-pointer">Transfer bank manual</summary>
                                <p class="text-sm text-slate-600 mt-3">Transfer <b>{{ $rp($booking->balance_due) }}</b> ke salah satu rekening berikut, lalu unggah bukti transfernya:</p>
                                <pre class="text-sm font-bold text-slate-800 bg-slate-50 rounded-xl p-3 mt-2 whitespace-pre-wrap font-sans">{{ $hotel->bank_accounts }}</pre>
                                <form method="POST" action="{{ route('customer.bookings.proof', $booking) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap gap-3 items-center"
                                    onsubmit="var b=this.querySelector('[type=submit]'); if(b.disabled){return false;} b.disabled=true; b.textContent='Mengunggah...';">
                                    @csrf
                                    <input type="file" name="proof" accept="image/*,application/pdf" required class="text-sm">
                                    <button type="submit" class="bg-slate-800 text-white px-5 py-2.5 rounded-xl text-sm font-black">Kirim bukti</button>
                                </form>
                            </details>
                        @endif

                        @if ((! $tripayEnabled || ! $channels) && ! $hotel->bank_accounts)
                            <p class="text-sm text-slate-500">Silakan hubungi hotel untuk instruksi pembayaran.</p>
                        @endif
                    @endif
                </section>
            @endif

            {{-- Review --}}
            @if ($booking->status === 'Completed')
                <section class="bg-white rounded-3xl border border-slate-100 shadow-lg shadow-slate-200/30 p-8">
                    <h2 class="text-lg font-extrabold text-slate-800 mb-4">{{ $booking->review ? 'Ulasan Anda' : 'Bagaimana pengalaman menginap Anda?' }}</h2>
                    <form method="POST" action="{{ route('customer.bookings.review', $booking) }}" class="flex flex-col gap-4">
                        @csrf
                        <div class="flex flex-row-reverse justify-end gap-1 text-3xl">
                            @for ($i = 5; $i >= 1; $i--)
                                <input type="radio" name="rating" id="star{{ $i }}" value="{{ $i }}" class="peer hidden" @checked(old('rating', $booking->review?->rating) == $i) required>
                                <label for="star{{ $i }}" class="cursor-pointer text-slate-300 peer-checked:text-yellow-400 hover:text-yellow-400 [&:hover~label]:text-yellow-400">&#9733;</label>
                            @endfor
                        </div>
                        <textarea name="comment" rows="3" maxlength="2000" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-800" placeholder="Ceritakan pengalaman Anda (opsional)">{{ old('comment', $booking->review?->comment) }}</textarea>
                        <button type="submit" class="self-start bg-blue-600 text-white px-6 py-3 rounded-xl text-sm font-black">{{ $booking->review ? 'Perbarui ulasan' : 'Kirim ulasan' }}</button>
                    </form>
                </section>
            @endif
        </div>

        <aside class="flex flex-col gap-6">
            <div class="bg-white rounded-3xl border border-slate-100 shadow-lg shadow-slate-200/30 p-6">
                <h2 class="text-lg font-extrabold text-slate-800 mb-4">Rincian biaya</h2>
                <dl class="text-sm space-y-2">
                    <div class="flex justify-between"><dt class="text-slate-500">Kamar {{ $booking->nights }} malam</dt><dd class="font-bold">{{ $rp($booking->room_total) }}</dd></div>
                    @if ((float) $booking->discount > 0)
                        <div class="flex justify-between text-green-600"><dt>Diskon {{ $booking->promoCode?->code }}</dt><dd class="font-bold">- {{ $rp($booking->discount) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-slate-500">Service charge</dt><dd class="font-bold">{{ $rp($booking->service_charge) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Pajak</dt><dd class="font-bold">{{ $rp($booking->tax) }}</dd></div>
                    @foreach ($booking->charges as $charge)
                        <div class="flex justify-between"><dt class="text-slate-500">{{ $charge->description }}</dt><dd class="font-bold">{{ $rp($charge->amount) }}</dd></div>
                    @endforeach
                    <div class="flex justify-between border-t border-slate-100 pt-3 mt-3"><dt class="font-bold">Total</dt><dd class="text-lg font-black text-slate-800">{{ $rp($booking->grand_total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Sudah dibayar</dt><dd class="font-bold text-green-600">{{ $rp($booking->amount_paid) }}</dd></div>
                    <div class="flex justify-between"><dt class="font-bold">Sisa</dt><dd class="text-xl font-black {{ $booking->balance_due > 0 ? 'text-orange-500' : 'text-green-600' }}">{{ $rp($booking->balance_due) }}</dd></div>
                </dl>
                <a href="{{ route('customer.bookings.invoice', $booking) }}" class="mt-5 block text-center rounded-xl border border-slate-200 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Unduh invoice (PDF)</a>
            </div>

            @if ($booking->status === 'Pending' && ! $pendingTransfer)
                <form method="POST" action="{{ route('customer.bookings.cancel', $booking) }}" onsubmit="return confirm('Batalkan reservasi {{ $booking->booking_code }}?');" class="text-center">
                    @csrf
                    <button type="submit" class="text-sm font-bold text-red-500 hover:underline">Batalkan reservasi</button>
                </form>
            @endif
        </aside>
    </div>
@endsection
