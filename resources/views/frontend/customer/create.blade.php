@extends('frontend.layout')
@section('title', 'Pesan ' . $roomType->name . ' - ' . $hotel->name)

@section('content')
    <a href="{{ route('rooms.search', ['check_in' => $checkIn->toDateString(), 'check_out' => $checkOut->toDateString(), 'guests' => $guests]) }}"
        class="inline-block text-sm font-bold text-blue-600 mb-6">&larr; Kembali ke pilihan kamar</a>

    <div class="grid lg:grid-cols-3 gap-8">
        <form method="POST" action="{{ route('customer.bookings.store', $roomType) }}" class="lg:col-span-2 bg-white rounded-3xl border border-slate-100 shadow-lg shadow-slate-200/30 p-8 flex flex-col gap-6"
            onsubmit="var b=this.querySelector('[type=submit]'); if(b.disabled){return false;} b.disabled=true; b.textContent='Memproses...';">
            @csrf
            <h1 class="text-2xl font-extrabold text-slate-800">Detail pemesanan</h1>

            <div class="grid sm:grid-cols-2 gap-4">
                <label class="block">
                    <span class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Check-in</span>
                    <input type="date" name="check_in" value="{{ old('check_in', $checkIn->toDateString()) }}" min="{{ today()->toDateString() }}" required
                        class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 font-bold text-slate-800">
                </label>
                <label class="block">
                    <span class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Check-out</span>
                    <input type="date" name="check_out" value="{{ old('check_out', $checkOut->toDateString()) }}" required
                        class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 font-bold text-slate-800">
                </label>
                <label class="block">
                    <span class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Jumlah tamu (maks. {{ $roomType->capacity }})</span>
                    <input type="number" name="guests" value="{{ old('guests', $guests) }}" min="1" max="{{ $roomType->capacity }}" required
                        class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 font-bold text-slate-800">
                </label>
                <label class="block">
                    <span class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">No. telepon / WhatsApp</span>
                    <input type="tel" name="guest_phone" value="{{ old('guest_phone', auth()->user()->phone ?? auth()->user()->no_wa) }}" required
                        class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 font-bold text-slate-800" placeholder="08xxxxxxxxxx">
                </label>
            </div>

            <label class="block">
                <span class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Permintaan khusus (opsional)</span>
                <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-800"
                    placeholder="mis. kasur twin, lantai atas, datang larut malam">{{ old('notes') }}</textarea>
            </label>

            <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-600">
                Pemesan: <span class="font-bold text-slate-800">{{ auth()->user()->name }}</span> ({{ auth()->user()->email }}).
                Check-in mulai pukul {{ substr($hotel->check_in_time, 0, 5) }}, check-out paling lambat {{ substr($hotel->check_out_time, 0, 5) }}.
                Reservasi berstatus <span class="font-bold">Pending</span> sampai dikonfirmasi hotel.
            </div>

            @if ($available < 1)
                <p class="text-sm font-bold text-red-600">Kamar ini sudah penuh untuk tanggal yang dipilih. Ubah tanggal lalu coba lagi.</p>
            @endif

            <button type="submit" class="self-start bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-10 py-4 rounded-2xl font-extrabold shadow-xl shadow-blue-600/30">
                Buat Reservasi
            </button>
        </form>

        <aside class="bg-white rounded-3xl border border-slate-100 shadow-lg shadow-slate-200/30 overflow-hidden h-fit">
            <img src="{{ $roomType->image_url }}" alt="" class="w-full h-48 object-cover">
            <div class="p-6">
                <h2 class="text-lg font-extrabold text-slate-800">{{ $roomType->name }}</h2>
                <p class="text-xs text-slate-400 font-bold mb-4">{{ $hotel->name }}</p>
                <dl class="text-sm space-y-2">
                    <div class="flex justify-between"><dt class="text-slate-500">Harga / malam</dt><dd class="font-bold">Rp {{ number_format($roomType->base_price, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Lama menginap</dt><dd class="font-bold">{{ $nights }} malam</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-3 mt-3"><dt class="font-bold">Total</dt><dd class="text-xl font-black text-orange-500">Rp {{ number_format($total, 0, ',', '.') }}</dd></div>
                </dl>
                <p class="text-[11px] text-slate-400 mt-3">Total dihitung ulang otomatis jika tanggal diubah.</p>
            </div>
        </aside>
    </div>
@endsection
