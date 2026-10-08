@extends('frontend.layout')
@section('title', $roomType->name . ' - ' . $hotel->name)

@php $photos = collect([$roomType->image_url])->merge($roomType->images->map->url); @endphp

@section('content')
    <a href="{{ route('landing') }}#kamar" class="inline-block text-sm font-bold text-blue-600 mb-6">&larr; Semua kamar</a>

    <div class="grid lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">
            <img src="{{ $photos->first() }}" alt="{{ $roomType->name }}" fetchpriority="high" class="w-full h-80 lg:h-[26rem] object-cover rounded-3xl mb-4">
            @if ($photos->count() > 1)
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-3 mb-8">
                    @foreach ($photos->skip(1) as $photo)
                        <a href="{{ $photo }}" target="_blank" rel="noopener"><img src="{{ $photo }}" alt="" loading="lazy" decoding="async" class="w-full h-24 object-cover rounded-xl hover:opacity-80"></a>
                    @endforeach
                </div>
            @endif

            <h1 class="text-3xl font-extrabold text-slate-800 mb-2">{{ $roomType->name }}</h1>
            <p class="text-sm font-bold text-slate-400 mb-6">
                Maks. {{ $roomType->capacity }} tamu
                @if ($reviews->isNotEmpty()) &middot; <span class="text-yellow-500">&#9733; {{ number_format($average, 1) }}</span> ({{ $reviews->count() }} ulasan) @endif
            </p>
            <p class="text-slate-600 leading-relaxed whitespace-pre-line mb-6">{{ $roomType->description }}</p>
            <div class="flex flex-wrap gap-2 mb-10">
                @foreach ($roomType->amenities ?? [] as $amenity)
                    <span class="text-xs font-bold bg-blue-50 text-blue-700 px-3 py-1.5 rounded-full">{{ $amenity }}</span>
                @endforeach
            </div>

            <h2 class="text-xl font-extrabold text-slate-800 mb-4">Ulasan tamu</h2>
            <div class="flex flex-col gap-4">
                @forelse ($reviews as $review)
                    <div class="bg-white rounded-2xl border border-slate-100 p-5">
                        <div class="flex justify-between gap-3 mb-1">
                            <span class="font-bold text-slate-800">{{ \Illuminate\Support\Str::words($review->user->name ?? 'Tamu', 2, '') }}</span>
                            <span class="text-yellow-400">{{ str_repeat('★', $review->rating) }}<span class="text-slate-200">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                        </div>
                        <p class="text-xs text-slate-400 mb-2">{{ $review->created_at->translatedFormat('F Y') }}</p>
                        @if ($review->comment)
                            <p class="text-sm text-slate-600">{{ $review->comment }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada ulasan untuk kamar ini.</p>
                @endforelse
            </div>
        </div>

        <aside class="bg-white rounded-3xl border border-slate-100 shadow-lg shadow-slate-200/30 p-6 h-fit lg:sticky lg:top-28">
            <p class="text-xs font-bold text-slate-400">Mulai dari</p>
            <p class="text-3xl font-black text-orange-500 mb-1">Rp {{ number_format($fromPrice, 0, ',', '.') }}<span class="text-sm text-slate-400 font-bold">/malam</span></p>
            <p class="text-xs text-slate-400 mb-6">Belum termasuk pajak {{ (float) $hotel->tax_percent }}% &amp; service {{ (float) $hotel->service_percent }}%.</p>
            @include('frontend.partials.search-form')
        </aside>
    </div>
@endsection
