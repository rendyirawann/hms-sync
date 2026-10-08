    <!-- Footer -->
    <footer class="bg-white border-t border-slate-100 pt-16 pb-10">
        <div class="container mx-auto px-4 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10 mb-12">
                <div>
                    <a href="{{ route('landing') }}" class="flex items-center gap-3 mb-5">
                        <img src="{{ asset('assets/media/landing/logo-landing.png') }}" alt="" class="h-10 w-auto" loading="lazy">
                        <span class="text-xl font-extrabold tracking-tight text-[#001e3c]">{{ $hotel->name ?? 'HMS' }}</span>
                    </a>
                    <p class="text-sm text-slate-500 leading-relaxed">{{ $hotel->address ?? '' }}</p>
                </div>
                <div>
                    <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-5">Kontak</h4>
                    <ul class="space-y-3 text-sm text-slate-500 font-bold">
                        @if (! empty($hotel?->phone))
                            <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $hotel->phone) }}" class="hover:text-blue-600">{{ $hotel->phone }}</a></li>
                        @endif
                        @if (! empty($hotel?->email))
                            <li><a href="mailto:{{ $hotel->email }}" class="hover:text-blue-600">{{ $hotel->email }}</a></li>
                        @endif
                        @if (! empty($hotel?->check_in_time))
                            <li>Check-in {{ substr($hotel->check_in_time, 0, 5) }} &middot; Check-out {{ substr($hotel->check_out_time, 0, 5) }}</li>
                        @endif
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest mb-5">Tautan</h4>
                    <ul class="space-y-3 text-sm text-slate-500 font-bold">
                        <li><a href="{{ route('landing') }}#kamar" class="hover:text-blue-600">Tipe Kamar</a></li>
                        <li><a href="{{ route('landing') }}#cari" class="hover:text-blue-600">Cek Ketersediaan</a></li>
                        <li><a href="{{ route('customer.bookings.index') }}" class="hover:text-blue-600">Reservasi Saya</a></li>
                    </ul>
                </div>
            </div>
            <div class="pt-8 border-t border-slate-100 text-xs text-slate-400 font-bold">
                &copy; {{ date('Y') }} {{ $hotel->name ?? 'HMS' }}. Semua hak dilindungi.
            </div>
        </div>
    </footer>
