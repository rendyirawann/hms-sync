<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $hotel->name . ' - Reservasi Kamar')</title>
    
    <link rel="shortcut icon" href="{{ asset('assets/media/logos/favicon.ico') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite('resources/css/app.css')

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .text-gradient {
            background: linear-gradient(90deg, #3b82f6 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-white text-slate-900 antialiased">

    @include('frontend.partials.header')

    <!-- Hero Section -->
    <section class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img fetchpriority="high" decoding="async" src="{{ $hotel->image_url ?? asset('assets/media/landing/hero-landing.png') }}" alt="{{ $hotel->name }}" class="w-full h-full object-cover object-center scale-105">
            <div class="absolute inset-0 bg-gradient-to-r from-white/40 via-transparent to-transparent"></div>
        </div>

        <div class="container mx-auto px-4 lg:px-8 relative z-10">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-3 bg-white/90 backdrop-blur-sm px-4 py-2 rounded-full shadow-sm mb-8 border border-slate-100">
                    <div class="w-8 h-8 rounded-full bg-[#001e3c] flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-7.714 2.143L11 21l-2.286-6.857L1 12l7.714-2.143L11 3z" />
                        </svg>
                    </div>
                    <div class="text-sm">
                        <span class="font-extrabold text-[#001e3c]">{{ $hotel->name }}</span>
                        @if ($hotel->rating > 0)
                            <span class="text-blue-600 font-bold ml-2">&#9733; {{ $hotel->rating }}</span>
                        @endif
                    </div>
                </div>

                <h1 class="text-6xl lg:text-[5.5rem] font-extrabold text-slate-900 leading-[1.05] mb-8">
                    Menginap Melampaui<br>Imajinasi <span class="text-gradient">Anda</span>
                </h1>
                <p class="text-xl text-slate-600 font-medium max-w-md leading-relaxed">
                    {{ $hotel->description ? \Illuminate\Support\Str::limit($hotel->description, 140) : 'Pesan kamar langsung di ' . $hotel->name . ' dengan harga terbaik.' }}
                </p>
            </div>
        </div>
    </section>

    <!-- Search Section -->
    <!-- Search Section -->
    <section id="cari" class="container mx-auto px-4 lg:px-8 -mt-24 relative z-20">
        <div class="bg-white rounded-3xl shadow-2xl shadow-slate-200/50 p-2 border border-slate-100 max-w-6xl">
            @include('frontend.partials.search-form')
        </div>
    </section>

    <!-- Room Types -->
    <section id="kamar" class="py-20 bg-slate-50/50">
        <div class="container mx-auto px-4 lg:px-8">
            <div class="flex items-end justify-between mb-12 gap-6">
                <div>
                    <p class="text-xs font-extrabold text-blue-600 uppercase tracking-widest mb-2">Pilihan kamar</p>
                    <h2 class="text-2xl font-extrabold text-slate-800">Kamar di {{ $hotel->name }}</h2>
                </div>
                <a href="#cari" class="text-blue-600 font-bold text-sm hover:underline">Cek ketersediaan</a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                @forelse ($roomTypes as $type)
                    <div class="bg-white rounded-[2rem] overflow-hidden shadow-xl shadow-slate-200/40 border border-slate-50 relative group flex flex-col">
                        <div class="h-56 overflow-hidden">
                            <img loading="lazy" decoding="async" src="{{ $type->image_url }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="{{ $type->name }}">
                        </div>
                        <div class="p-6 flex flex-col flex-1">
                            <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">Maks. {{ $type->capacity }} tamu</p>
                            <h4 class="text-lg font-extrabold text-slate-800 mb-2 truncate"><a href="{{ route('rooms.show', $type) }}" class="hover:text-blue-600">{{ $type->name }}</a></h4>
                            @if ($type->reviews_avg_rating)
                                <p class="text-xs font-bold text-yellow-500 mb-2">&#9733; {{ number_format($type->reviews_avg_rating, 1) }}</p>
                            @endif
                            <p class="text-xs text-slate-500 mb-4">{{ implode(' · ', array_slice($type->amenities ?? [], 0, 4)) }}</p>
                            <div class="mt-auto flex items-end justify-between">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400">Mulai dari</p>
                                    <p class="text-lg font-black text-orange-500">Rp {{ number_format($fromPrice[$type->id] ?? $type->base_price, 0, ',', '.') }}<span class="text-xs text-slate-400 font-bold">/malam</span></p>
                                </div>
                                <a href="{{ route('rooms.show', $type) }}" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-xs font-black hover:bg-blue-700">Lihat</a>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="col-span-full text-center text-slate-500 py-10">Tipe kamar belum tersedia. Silakan kembali lagi nanti.</p>
                @endforelse
            </div>
        </div>
    </section>

    @if ($gallery->isNotEmpty())
    <!-- Facilities gallery -->
    <section id="fasilitas" class="py-20">
        <div class="container mx-auto px-4 lg:px-8">
            <p class="text-xs font-extrabold text-blue-600 uppercase tracking-widest mb-2">Fasilitas</p>
            <h2 class="text-2xl font-extrabold text-slate-800 mb-10">Suasana {{ $hotel->name }}</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach ($gallery as $image)
                    <figure class="relative overflow-hidden rounded-2xl {{ $loop->first ? 'md:col-span-2 md:row-span-2' : '' }}">
                        <img src="{{ $image->url }}" alt="{{ $image->caption }}" loading="lazy" decoding="async" class="w-full h-full min-h-40 object-cover">
                        @if ($image->caption)
                            <figcaption class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/60 to-transparent p-3 text-xs font-bold text-white">{{ $image->caption }}</figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    @if ($reviews->isNotEmpty())
    <!-- Reviews -->
    <section id="ulasan" class="py-20 bg-slate-50/50">
        <div class="container mx-auto px-4 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-10">
                <div>
                    <p class="text-xs font-extrabold text-blue-600 uppercase tracking-widest mb-2">Kata tamu kami</p>
                    <h2 class="text-2xl font-extrabold text-slate-800">Ulasan tamu</h2>
                </div>
                <p class="text-sm font-bold text-slate-600"><span class="text-3xl font-black text-yellow-500">{{ number_format((float) $rating->average, 1) }}</span> / 5 dari {{ $rating->total }} ulasan</p>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($reviews as $review)
                    <div class="bg-white rounded-3xl border border-slate-100 p-6 shadow-lg shadow-slate-200/30">
                        <p class="text-yellow-400 mb-3">{{ str_repeat('★', $review->rating) }}<span class="text-slate-200">{{ str_repeat('★', 5 - $review->rating) }}</span></p>
                        <p class="text-sm text-slate-600 mb-4">{{ \Illuminate\Support\Str::limit($review->comment ?: 'Menginap yang menyenangkan.', 220) }}</p>
                        <p class="text-xs font-bold text-slate-800">{{ \Illuminate\Support\Str::words($review->user->name ?? 'Tamu', 2, '') }}</p>
                        <p class="text-[11px] text-slate-400">{{ $review->roomType->name ?? '' }} &middot; {{ $review->created_at->translatedFormat('F Y') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Value Propositions -->
    <section class="py-20 border-t border-slate-50">
        <div class="container mx-auto px-4 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="flex items-center gap-5 group">
                    <div class="w-14 h-14 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 group-hover:bg-blue-50 group-hover:text-blue-600 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <h5 class="text-sm font-black text-slate-800">Jaminan Harga Terbaik</h5>
                        <p class="text-[11px] text-slate-500 font-bold">Temukan harga lebih murah? Kami menyamainya.</p>
                    </div>
                </div>
                <div class="flex items-center gap-5 group">
                    <div class="w-14 h-14 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 group-hover:bg-green-50 group-hover:text-green-600 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h5 class="text-sm font-black text-slate-800">Pemesanan Fleksibel</h5>
                        <p class="text-[11px] text-slate-500 font-bold">Pembatalan gratis di banyak kamar.</p>
                    </div>
                </div>
                <div class="flex items-center gap-5 group">
                    <div class="w-14 h-14 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 group-hover:bg-purple-50 group-hover:text-purple-600 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div>
                        <h5 class="text-sm font-black text-slate-800">Bantuan 24/7</h5>
                        <p class="text-[11px] text-slate-500 font-bold">Kami siap membantu kapan saja.</p>
                    </div>
                </div>
                <div class="flex items-center gap-5 group">
                    <div class="w-14 h-14 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 group-hover:bg-red-50 group-hover:text-red-600 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 118 0v4h-8z" />
                        </svg>
                    </div>
                    <div>
                        <h5 class="text-sm font-black text-slate-800">Pembayaran Aman</h5>
                        <p class="text-[11px] text-slate-500 font-bold">Keamanan transaksi Anda prioritas kami.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>


    @include('frontend.partials.footer')
</body>
</html>
