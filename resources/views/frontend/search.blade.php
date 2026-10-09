@extends('frontend.layout')
@section('title', ($q ? 'Hotel di ' . $q : 'Cari hotel') . ' - ' . $brand['name'])

@section('content')
    <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/40 border border-slate-100 p-2 mb-10">
        @include('frontend.partials.search-form')
    </div>

    <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-800">
                {{ $results->count() }} hotel tersedia{{ $q ? ' untuk "' . $q . '"' : '' }}
            </h1>
            <p class="text-sm text-slate-500 font-medium">
                {{ $checkIn->translatedFormat('d M Y') }} &ndash; {{ $checkOut->translatedFormat('d M Y') }}
                &middot; {{ $nights }} malam &middot; {{ $guests }} tamu &middot; harga total termasuk pajak &amp; service
            </p>
        </div>
    </div>

    <div class="flex flex-col gap-6">
        @forelse ($results as $row)
            @php $hotel = $row['hotel']; $offer = $row['cheapest']; @endphp
            <a href="{{ route('hotels.show', ['hotel' => $hotel, 'check_in' => $checkIn->toDateString(), 'check_out' => $checkOut->toDateString(), 'guests' => $guests]) }}"
                class="hms-card bg-white rounded-3xl overflow-hidden border border-slate-100 shadow-lg shadow-slate-200/30 flex flex-col md:flex-row group">
                <img loading="lazy" decoding="async" src="{{ $hotel->cover_url }}" alt="{{ $hotel->name }}" class="md:w-80 h-52 md:h-auto object-cover">
                <div class="p-6 flex-1 flex flex-col md:flex-row gap-6">
                    <div class="flex-1 min-w-0">
                        <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">{{ $hotel->city }}</p>
                        <h2 class="text-xl font-extrabold text-slate-800 mb-1 group-hover:text-blue-600">{{ $hotel->name }}</h2>
                        <p class="text-sm text-slate-500 mb-3">{{ \Illuminate\Support\Str::limit($hotel->address, 90) }}</p>
                        @if ($hotel->reviews_avg_rating)
                            <p class="text-sm font-bold text-yellow-500 mb-3">&#9733; {{ number_format($hotel->reviews_avg_rating, 1) }}</p>
                        @endif
                        <p class="text-sm text-slate-600">
                            Termurah: <span class="font-bold">{{ $offer['type']->name }}</span>
                            &middot; {{ $row['types'] }} tipe kamar tersedia
                        </p>
                    </div>
                    <div class="md:w-56 md:text-right flex flex-col md:items-end justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold text-slate-400">mulai</p>
                            <p class="text-2xl font-black text-orange-500">Rp {{ number_format($offer['quote']['total'], 0, ',', '.') }}</p>
                            <p class="text-xs text-slate-400 font-bold">total {{ $nights }} malam</p>
                        </div>
                        @if ($row['rooms_left'] <= 3)
                            <span class="text-xs font-bold text-red-500">Sisa {{ $row['rooms_left'] }} kamar!</span>
                        @endif
                        <span class="hms-btn bg-blue-600 text-white px-6 py-3 rounded-xl text-sm font-black text-center group-hover:bg-blue-700">Lihat kamar</span>
                    </div>
                </div>
            </a>
        @empty
            <div class="bg-white rounded-3xl border border-slate-100 p-16 text-center">
                <p class="text-slate-500 mb-2">Tidak ada hotel dengan kamar kosong untuk pencarian ini.</p>
                <p class="text-sm text-slate-400">Coba ubah tanggal, jumlah tamu, atau kata kunci tujuan.</p>
            </div>
        @endforelse
    </div>
@endsection
