    <!-- Footer -->
    <footer class="bg-white border-t border-slate-100 pt-16 pb-10">
        <div class="container mx-auto px-4 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10 mb-12">
                <div>
                    <a href="{{ route('landing') }}" class="flex items-center gap-3 mb-5">
                        <img src="{{ asset('assets/media/branding/favicon-192.png') }}" alt="" width="40" height="40" class="h-10 w-10 hms-logo" loading="lazy">
                        <span class="text-xl font-extrabold tracking-tight text-[#001e3c]">{{ $brand['name'] }}</span>
                    </a>
                    <p class="text-sm text-slate-500 leading-relaxed">{{ $brand['description'] }}</p>
                </div>
                <div>
                    <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-5">Untuk tamu</h4>
                    <ul class="space-y-3 text-sm text-slate-500 font-bold">
                        <li><a href="{{ route('landing') }}#cari" class="hover:text-blue-600">Cari hotel</a></li>
                        <li><a href="{{ route('customer.bookings.index') }}" class="hover:text-blue-600">Reservasi Saya</a></li>
                        <li><a href="{{ route('verification.resend') }}" class="hover:text-blue-600">Kirim ulang aktivasi akun</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-5">Untuk hotel</h4>
                    <ul class="space-y-3 text-sm text-slate-500 font-bold">
                        <li><a href="{{ route('partner.register') }}" class="hover:text-blue-600">Daftarkan hotel Anda</a></li>
                        <li><a href="{{ route('login') }}" class="hover:text-blue-600">Masuk panel mitra</a></li>
                    </ul>
                </div>
            </div>
            <div class="pt-8 border-t border-slate-100 text-xs text-slate-400 font-bold">
                &copy; {{ date('Y') }} {{ $brand['name'] }}. Semua hak dilindungi.
            </div>
        </div>
    </footer>
