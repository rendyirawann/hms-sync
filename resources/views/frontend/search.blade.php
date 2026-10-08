@extends('frontend.layout')
@section('title', 'Ketersediaan Kamar - ' . $hotel->name)

@section('content')
    <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/40 border border-slate-100 p-2 mb-10">
        @include('frontend.partials.search-form')
    </div>

    <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-800">Kamar tersedia</h1>
            <p class="text-sm text-slate-500 font-medium">
                {{ $checkIn->translatedFormat('d M Y') }} &ndash; {{ $checkOut->translatedFormat('d M Y') }}
                &middot; {{ $nights }} malam &middot; {{ $guests }} tamu
            </p>
        </div>
    </div>

    <div class="flex flex-col gap-6">
        @forelse ($results as $row)
            @php $type = $row['type']; $bookable = $row['available'] > 0 && $row['fits']; @endphp
            <div class="bg-white rounded-3xl overflow-hidden border border-slate-100 shadow-lg shadow-slate-200/30 flex flex-col md:flex-row {{ $bookable ? '' : 'opacity-70' }}">
                <img loading="lazy" decoding="async" src="{{ $type->image_url }}" alt="{{ $type->name }}" class="md:w-72 h-52 md:h-auto object-cover">
                <div class="p-6 flex-1 flex flex-col md:flex-row gap-6">
                    <div class="flex-1">
                        <h3 class="text-xl font-extrabold text-slate-800 mb-1">{{ $type->name }}</h3>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Maks. {{ $type->capacity }} tamu</p>
                        <p class="text-sm text-slate-600 mb-4">{{ \Illuminate\Support\Str::limit($type->description, 180) }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($type->amenities ?? [] as $amenity)
                                <span class="text-xs font-bold bg-blue-50 text-blue-700 px-3 py-1 rounded-full">{{ $amenity }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="md:w-56 md:text-right flex flex-col md:items-end justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold text-slate-400">Rp {{ number_format($type->base_price, 0, ',', '.') }} / malam</p>
                            <p class="text-2xl font-black text-orange-500">Rp {{ number_format($row['total'], 0, ',', '.') }}</p>
                            <p class="text-xs text-slate-400 font-bold">total {{ $nights }} malam</p>
                        </div>
                        @if (! $row['fits'])
                            <span class="text-sm font-bold text-slate-500">Kapasitas tidak cukup untuk {{ $guests }} tamu</span>
                        @elseif ($row['available'] < 1)
                            <span class="text-sm font-bold text-red-500">Penuh di tanggal ini</span>
                        @else
                            @if ($row['available'] <= 3)
                                <span class="text-xs font-bold text-red-500">Sisa {{ $row['available'] }} kamar!</span>
                            @endif
                            <a href="{{ route('customer.bookings.create', ['roomType' => $type, 'check_in' => $checkIn->toDateString(), 'check_out' => $checkOut->toDateString(), 'guests' => $guests]) }}"
                                class="bg-blue-600 text-white px-6 py-3 rounded-xl text-sm font-black hover:bg-blue-700 text-center">Pesan Sekarang</a>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="text-center text-slate-500 py-16">Belum ada tipe kamar yang dijual.</p>
        @endforelse
    </div>
@endsection
