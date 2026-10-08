    <!-- Header / Navbar -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-100">
        <div class="container mx-auto px-4 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <a href="{{ route('landing') }}" class="flex items-center gap-3">
                    <img src="{{ asset('assets/media/landing/logo-landing.png') }}" alt="" class="h-10 w-auto">
                    <span class="text-xl font-extrabold tracking-tight text-[#001e3c] truncate max-w-[10rem] sm:max-w-[16rem] lg:max-w-[22rem]">{{ $hotel->name ?? 'HMS' }}</span>
                </a>

                <nav class="hidden lg:flex items-center gap-8">
                    <a href="{{ route('landing') }}" class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition-colors">Beranda</a>
                    <a href="{{ route('landing') }}#kamar" class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition-colors">Kamar</a>
                    <a href="{{ route('landing') }}#cari" class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition-colors">Cek Ketersediaan</a>
                    @auth
                        @if (auth()->user()->isCustomer())
                            <a href="{{ route('customer.bookings.index') }}" class="text-sm font-bold text-blue-600">Reservasi Saya</a>
                        @endif
                    @endauth
                </nav>

                <div class="flex items-center gap-3">
                    @auth
                        @if (auth()->user()->isCustomer())
                            <a href="{{ route('customer.profile') }}" class="hidden sm:inline text-sm font-bold text-slate-700 hover:text-blue-600">{{ \Illuminate\Support\Str::words(auth()->user()->name, 2, '') }}</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="bg-slate-100 text-slate-700 px-5 py-2.5 rounded-full text-sm font-bold hover:bg-slate-200 transition-all">Keluar</button>
                            </form>
                        @else
                            <a href="{{ route('dashboard') }}" class="bg-[#001e3c] text-white px-8 py-2.5 rounded-full text-sm font-bold shadow-lg shadow-blue-900/20 hover:bg-blue-900 transition-all">Dashboard</a>
                        @endif
                    @else
                        @if (Route::has('register') && \App\Models\Setting::get('allow_registration', '0') === '1')
                            <a href="{{ route('register') }}" class="hidden sm:inline text-sm font-bold text-slate-700 hover:text-blue-600">Daftar</a>
                        @endif
                        <a href="{{ route('login') }}" class="bg-[#001e3c] text-white px-8 py-2.5 rounded-full text-sm font-bold shadow-lg shadow-blue-900/20 hover:bg-blue-900 transition-all">Masuk</a>
                    @endauth
                </div>
            </div>
        </div>
    </header>
