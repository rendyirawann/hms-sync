<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $brand['name'] }} - Pesan Hotel di Seluruh Indonesia</title>
    
    <link rel="icon" href="{{ $brand['favicon_url'] }}" sizes="any" />
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $brand['favicon_png_url'] }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $brand['apple_icon_url'] }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite('resources/css/app.css')
    <link rel="stylesheet" href="{{ asset('assets/css/hms-motion.css') }}">

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

    <!-- Hero -->
    <section class="relative pt-32 pb-20 lg:pt-44 lg:pb-32 overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="{{ asset('assets/media/landing/hero-landing.png') }}" alt="" fetchpriority="high" decoding="async" class="w-full h-full object-cover object-center hms-hero-pan">
            <div class="absolute inset-0 bg-gradient-to-r from-white/60 via-white/10 to-transparent"></div>
        </div>
        <div class="container mx-auto px-4 lg:px-8 relative z-10">
            <div class="max-w-2xl">
                <div class="hms-rise hms-rise-1 inline-flex items-center gap-3 bg-white/90 backdrop-blur-sm px-4 py-2 rounded-full shadow-sm mb-8 border border-slate-100"><span class="hms-float inline-flex items-center gap-3">
                    <span class="font-extrabold text-[#001e3c] text-sm">{{ \App\Models\Hotel::listed()->count() }} hotel mitra</span>
                    @if ($cities->isNotEmpty())
                        <span class="text-blue-600 font-bold text-sm">di {{ $cities->count() }} kota</span>
                    @endif
                </span></div>
                <h1 class="hms-rise hms-rise-2 text-5xl lg:text-[5rem] font-extrabold text-slate-900 leading-[1.05] mb-8">
                    Temukan hotel<br>untuk perjalanan <span class="text-gradient hms-shimmer">Anda</span>
                </h1>
                <p class="hms-rise hms-rise-3 text-xl text-slate-700 font-medium max-w-md leading-relaxed">
                    Bandingkan harga dan ketersediaan langsung dari hotel, bayar online, dan terima kode pintu kamar di ponsel saat check-in.
                </p>
            </div>
        </div>
    </section>

    <!-- Search -->
    <section id="cari" class="container mx-auto px-4 lg:px-8 -mt-24 relative z-20">
        <div class="hms-rise hms-rise-4 bg-white rounded-3xl shadow-2xl shadow-slate-200/50 p-2 border border-slate-100 max-w-6xl">
            @include('frontend.partials.search-form')
            <datalist id="hms-cities">
                @foreach ($cities as $city)
                    <option value="{{ $city->city }}"></option>
                @endforeach
            </datalist>
        </div>
        @if ($cities->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2 mt-6 max-w-6xl">
                <span class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mr-2">Kota populer</span>
                @foreach ($cities as $city)
                    <a href="{{ route('rooms.search', ['q' => $city->city, 'check_in' => today()->toDateString(), 'check_out' => today()->addDay()->toDateString(), 'guests' => 2]) }}"
                        class="hms-btn px-4 py-2 rounded-full bg-white border border-slate-200 text-sm font-bold text-slate-700 hover:border-blue-400 hover:text-blue-600">
                        {{ $city->city }} <span class="text-slate-400 font-semibold">· {{ $city->total }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <!-- Hotels -->
    <section id="hotel" class="py-20">
        <div class="container mx-auto px-4 lg:px-8">
            <div class="flex items-end justify-between mb-12 gap-6">
                <div>
                    <p class="text-xs font-extrabold text-blue-600 uppercase tracking-widest mb-2">Hotel pilihan</p>
                    <h2 class="text-2xl font-extrabold text-slate-800">Menginap di hotel mitra kami</h2>
                </div>
                <a href="#cari" class="text-blue-600 font-bold text-sm hover:underline">Cek ketersediaan</a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                @forelse ($hotels as $hotel)
                    <a href="{{ route('hotels.show', $hotel) }}" class="hms-card bg-white rounded-[2rem] overflow-hidden shadow-xl shadow-slate-200/40 border border-slate-50 group flex flex-col">
                        <div class="h-52 overflow-hidden">
                            <img loading="lazy" decoding="async" src="{{ $hotel->cover_url }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="{{ $hotel->name }}">
                        </div>
                        <div class="p-6 flex flex-col flex-1">
                            <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">{{ $hotel->city ?: '-' }}</p>
                            <h3 class="text-lg font-extrabold text-slate-800 mb-2 truncate group-hover:text-blue-600">{{ $hotel->name }}</h3>
                            <p class="text-xs font-bold text-slate-500 mb-4">
                                @if ($hotel->reviews_avg_rating)
                                    <span class="text-yellow-500">&#9733; {{ number_format($hotel->reviews_avg_rating, 1) }}</span> ({{ $hotel->reviews_count }} ulasan)
                                @else
                                    Belum ada ulasan
                                @endif
                            </p>
                            <div class="mt-auto">
                                <p class="text-[10px] font-bold text-slate-400">Mulai dari</p>
                                <p class="text-lg font-black text-orange-500">
                                    @if ($hotel->room_types_min_base_price)
                                        Rp {{ number_format($hotel->room_types_min_base_price, 0, ',', '.') }}<span class="text-xs text-slate-400 font-bold">/malam</span>
                                    @else
                                        <span class="text-sm text-slate-400">Segera hadir</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </a>
                @empty
                    <p class="col-span-full text-center text-slate-500 py-10">Belum ada hotel yang tayang. Pemilik hotel? <a href="{{ route('partner.register') }}" class="text-blue-600 font-bold">Daftarkan hotel Anda</a>.</p>
                @endforelse
            </div>
        </div>
    </section>

    @if ($reviews->isNotEmpty())
    <!-- Reviews -->
    <section id="ulasan" class="py-20 bg-slate-50/50">
        <div class="container mx-auto px-4 lg:px-8">
            <p class="text-xs font-extrabold text-blue-600 uppercase tracking-widest mb-2">Kata tamu</p>
            <h2 class="text-2xl font-extrabold text-slate-800 mb-10">Ulasan terbaru</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($reviews as $review)
                    <div class="bg-white rounded-3xl border border-slate-100 p-6 shadow-lg shadow-slate-200/30">
                        <p class="text-yellow-400 mb-3">{{ str_repeat('★', $review->rating) }}<span class="text-slate-200">{{ str_repeat('★', 5 - $review->rating) }}</span></p>
                        <p class="text-sm text-slate-600 mb-4">{{ \Illuminate\Support\Str::limit($review->comment ?: 'Menginap yang menyenangkan.', 220) }}</p>
                        <p class="text-xs font-bold text-slate-800">{{ \Illuminate\Support\Str::words($review->user->name ?? 'Tamu', 2, '') }}</p>
                        <a href="{{ route('hotels.show', $review->hotel) }}" class="text-[11px] font-bold text-blue-600">{{ $review->hotel->name }}</a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Partner CTA -->
    <section class="py-16">
        <div class="container mx-auto px-4 lg:px-8">
            <div class="rounded-[2rem] bg-[#001e3c] text-white p-10 lg:p-14 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-8">
                <div class="max-w-xl">
                    <p class="text-xs font-extrabold text-blue-300 uppercase tracking-widest mb-3">Untuk pemilik hotel</p>
                    <h2 class="text-3xl font-extrabold mb-3">Jual kamar Anda di {{ $brand['name'] }}</h2>
                    <p class="text-slate-300">Panel front desk, housekeeping, shift pegawai, dan laporan sudah termasuk. Pembayaran tamu kami terima dan setorkan rutin ke rekening hotel Anda.</p>
                </div>
                <a href="{{ route('partner.register') }}" class="hms-btn bg-white text-[#001e3c] px-8 py-4 rounded-2xl font-extrabold whitespace-nowrap">Daftarkan hotel</a>
            </div>
        </div>
    </section>

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
