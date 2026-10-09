{{-- Marketplace search (destination + dates) or, with $searchHotel, one hotel's availability. --}}
@php
    $searchHotel = $searchHotel ?? null;
    $ci = old('check_in', isset($checkIn) ? $checkIn->toDateString() : (isset($stay) && $stay ? $stay['check_in']->toDateString() : today()->toDateString()));
    $co = old('check_out', isset($checkOut) ? $checkOut->toDateString() : (isset($stay) && $stay ? $stay['check_out']->toDateString() : today()->addDay()->toDateString()));
    $gs = old('guests', $guests ?? (isset($stay) && $stay ? $stay['guests'] : 2));
@endphp

<form method="GET" action="{{ $searchHotel ? route('hotels.show', $searchHotel) : route('rooms.search') }}" class="flex flex-col lg:flex-row items-stretch gap-4 p-4">
    <div class="flex-1 grid grid-cols-1 md:grid-cols-2 {{ $searchHotel ? 'lg:grid-cols-3' : 'lg:grid-cols-4' }} gap-4">
        @unless ($searchHotel)
            <label class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100 hover:border-blue-200 transition-all block cursor-pointer">
                <span class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Tujuan</span>
                <input type="text" name="q" value="{{ old('q', $q ?? '') }}" placeholder="Kota atau nama hotel" list="hms-cities"
                    class="bg-transparent border-0 p-0 text-sm font-bold text-slate-800 w-full focus:ring-0 focus:outline-none placeholder:text-slate-400">
            </label>
        @endunless
        <label class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100 hover:border-blue-200 transition-all block cursor-pointer">
            <span class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Check-in</span>
            <input type="date" name="check_in" value="{{ $ci }}" min="{{ today()->toDateString() }}" required
                class="bg-transparent border-0 p-0 text-sm font-bold text-slate-800 w-full focus:ring-0 focus:outline-none">
        </label>
        <label class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100 hover:border-blue-200 transition-all block cursor-pointer">
            <span class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Check-out</span>
            <input type="date" name="check_out" value="{{ $co }}" min="{{ today()->addDay()->toDateString() }}" required
                class="bg-transparent border-0 p-0 text-sm font-bold text-slate-800 w-full focus:ring-0 focus:outline-none">
        </label>
        <label class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100 hover:border-blue-200 transition-all block cursor-pointer">
            <span class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Jumlah tamu</span>
            <input type="number" name="guests" value="{{ $gs }}" min="1" max="20" required
                class="bg-transparent border-0 p-0 text-sm font-bold text-slate-800 w-full focus:ring-0 focus:outline-none">
        </label>
    </div>
    <button type="submit" class="hms-btn bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-10 py-5 rounded-2xl font-extrabold text-base shadow-xl shadow-blue-600/30 hover:scale-[1.02] active:scale-95 transition-all">
        {{ $searchHotel ? 'Cek Kamar' : 'Cari Hotel' }}
    </button>
</form>
