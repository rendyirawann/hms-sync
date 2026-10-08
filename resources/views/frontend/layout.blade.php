@include('frontend.partials.head')

    @include('frontend.partials.header')

    <main class="pt-28 pb-20 min-h-[70vh] bg-slate-50/50">
        <div class="container mx-auto px-4 lg:px-8">
            @if (session('success'))
                <div class="mb-6 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-bold text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-bold text-red-700">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                    <p class="font-bold mb-1">Periksa kembali isian Anda:</p>
                    <ul class="list-disc ps-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    @include('frontend.partials.footer')

    @auth
        @if (auth()->user()->isCustomer() && request()->routeIs('customer.bookings.index', 'customer.bookings.show'))
            @include('partials.realtime')
            <script>
                // Payment confirmed / room ready while the guest watches the page.
                window.addEventListener('echo:ready', function () {
                    var watching = @json(request()->route('booking')?->id);
                    window.Echo.private(@json('App.Models.User.' . auth()->id())).listen('.booking.updated', function (e) {
                        if (watching && e.id !== watching) { return; }
                        var typing = document.activeElement && ['INPUT', 'TEXTAREA', 'SELECT'].indexOf(document.activeElement.tagName) !== -1;
                        if (!typing) { window.location.reload(); }
                    });
                });
            </script>
        @endif
    @endauth
</body>
</html>
