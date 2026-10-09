@extends('frontend.layout')
@section('title', $hotel->name . ' - ' . $brand['name'])

@section('content')
    @unless ($hotel->isBookable())
        <div class="mb-6 rounded-2xl border border-yellow-200 bg-yellow-50 px-5 py-4 text-sm font-bold text-yellow-800">
            Pratinjau: hotel ini belum tampil untuk tamu ({{ $hotel->approval_label }}).
        </div>
    @endunless

    <div class="grid lg:grid-cols-3 gap-8 mb-10">
        <div class="lg:col-span-2">
            <img src="{{ $hotel->cover_url }}" alt="{{ $hotel->name }}" fetchpriority="high" class="w-full h-72 lg:h-96 object-cover rounded-3xl mb-4">
            @if ($hotel->images->isNotEmpty())
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
                    @foreach ($hotel->images->take(5) as $image)
                        <a href="{{ $image->url }}" target="_blank" rel="noopener" title="{{ $image->caption }}">
                            <img src="{{ $image->url }}" alt="{{ $image->caption }}" loading="lazy" decoding="async" class="w-full h-24 object-cover rounded-xl hover:opacity-80">
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
        <aside class="bg-white rounded-3xl border border-slate-100 shadow-lg shadow-slate-200/30 p-6 h-fit flex flex-col gap-4">
            <div>
                <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">{{ $hotel->city }}{{ $hotel->province ? ', ' . $hotel->province : '' }}</p>
                <h1 class="text-2xl font-extrabold text-slate-800">{{ $hotel->name }}</h1>
                @if ($rating->total)
                    <p class="text-sm font-bold text-yellow-500 mt-1">&#9733; {{ number_format((float) $rating->average, 1) }} <span class="text-slate-400">({{ $rating->total }} ulasan)</span></p>
                @endif
            </div>
            <p class="text-sm text-slate-600">{{ $hotel->address }}</p>
            <dl class="text-sm grid grid-cols-2 gap-2">
                <dt class="text-slate-400 font-bold">Check-in</dt><dd class="font-bold text-slate-700">mulai {{ substr($hotel->check_in_time, 0, 5) }}</dd>
                <dt class="text-slate-400 font-bold">Check-out</dt><dd class="font-bold text-slate-700">s/d {{ substr($hotel->check_out_time, 0, 5) }}</dd>
            </dl>
            @if ($hotel->description)
                <p class="text-sm text-slate-600 leading-relaxed whitespace-pre-line">{{ \Illuminate\Support\Str::limit($hotel->description, 400) }}</p>
            @endif
        </aside>
    </div>

    <section id="kamar" class="mb-12">
        <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/40 border border-slate-100 p-2 mb-8">
            @include('frontend.partials.search-form', ['searchHotel' => $hotel])
        </div>

        <h2 class="text-xl font-extrabold text-slate-800 mb-1">Pilihan kamar</h2>
        <p class="text-sm text-slate-500 mb-6">
            @if ($stay)
                {{ $stay['check_in']->translatedFormat('d M Y') }} &ndash; {{ $stay['check_out']->translatedFormat('d M Y') }}
                &middot; {{ $stay['check_in']->diffInDays($stay['check_out']) }} malam &middot; {{ $stay['guests'] }} tamu
            @else
                Isi tanggal menginap untuk melihat ketersediaan dan harga total.
            @endif
        </p>

        <div class="flex flex-col gap-6">
            @forelse ($rooms as $row)
                @php $type = $row['type']; @endphp
                <div class="bg-white rounded-3xl overflow-hidden border border-slate-100 shadow-lg shadow-slate-200/30 flex flex-col md:flex-row">
                    <img loading="lazy" decoding="async" src="{{ $type->image_url }}" alt="{{ $type->name }}" class="md:w-72 h-52 md:h-auto object-cover">
                    <div class="p-6 flex-1 flex flex-col md:flex-row gap-6">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-xl font-extrabold text-slate-800 mb-1"><a href="{{ route('rooms.show', [$hotel, $type]) }}" class="hover:text-blue-600">{{ $type->name }}</a></h3>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Maks. {{ $type->capacity }} tamu</p>
                            <p class="text-sm text-slate-600 mb-4">{{ \Illuminate\Support\Str::limit($type->description, 180) }}</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($type->amenities ?? [] as $amenity)
                                    <span class="text-xs font-bold bg-blue-50 text-blue-700 px-3 py-1 rounded-full">{{ $amenity }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="md:w-56 md:text-right flex flex-col md:items-end justify-between gap-3">
                            @if ($stay)
                                <div>
                                    <p class="text-2xl font-black text-orange-500">Rp {{ number_format($row['quote']['total'], 0, ',', '.') }}</p>
                                    <p class="text-xs text-slate-400 font-bold">total, termasuk pajak &amp; service</p>
                                </div>
                                @if (! $row['fits'])
                                    <span class="text-sm font-bold text-slate-500">Kapasitas tidak cukup</span>
                                @elseif ($row['available'] < 1)
                                    <span class="text-sm font-bold text-red-500">Penuh di tanggal ini</span>
                                @elseif ($hotel->isBookable())
                                    @if ($row['available'] <= 3)
                                        <span class="text-xs font-bold text-red-500">Sisa {{ $row['available'] }} kamar!</span>
                                    @endif
                                    <a href="{{ route('customer.bookings.create', ['roomType' => $type, 'check_in' => $stay['check_in']->toDateString(), 'check_out' => $stay['check_out']->toDateString(), 'guests' => $stay['guests']]) }}"
                                        class="hms-btn bg-blue-600 text-white px-6 py-3 rounded-xl text-sm font-black hover:bg-blue-700 text-center">Pesan Sekarang</a>
                                @endif
                            @else
                                <div>
                                    <p class="text-xs font-bold text-slate-400">mulai</p>
                                    <p class="text-2xl font-black text-orange-500">Rp {{ number_format($row['from'], 0, ',', '.') }}<span class="text-xs text-slate-400 font-bold">/malam</span></p>
                                </div>
                                <a href="#kamar" class="border border-blue-200 text-blue-600 px-6 py-3 rounded-xl text-sm font-black text-center">Pilih tanggal</a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-slate-500 py-10 text-center">Hotel ini belum menambahkan tipe kamar.</p>
            @endforelse
        </div>
    </section>

    @if ($reviews->isNotEmpty())
        <section>
            <h2 class="text-xl font-extrabold text-slate-800 mb-6">Ulasan tamu</h2>
            <div class="grid md:grid-cols-2 gap-4">
                @foreach ($reviews as $review)
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
                @endforeach
            </div>
        </section>
    @endif
@endsection
