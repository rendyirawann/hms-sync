{{-- Laravel Echo over Reverb. Loaded only when broadcasting is set to
     Reverb and someone is signed in (private channels need a session).
     Local copies of pusher-js/echo, so the CSP never blocks them. --}}
@php
    $reverb = config('broadcasting.connections.reverb');
@endphp
@if (auth()->check() && config('broadcasting.default') === 'reverb' && ! empty($reverb['key']))
    <script src="{{ asset('assets/js/pusher.min.js') }}" defer></script>
    <script src="{{ asset('assets/js/echo.iife.js') }}" defer></script>
    <script>
        window.addEventListener('DOMContentLoaded', function () {
            if (typeof Echo === 'undefined' || typeof Pusher === 'undefined') { return; }
            var scheme = @json($reverb['options']['scheme'] ?? 'https');
            var port = Number(@json($reverb['options']['port'] ?? 443));
            window.Echo = new Echo({
                broadcaster: 'reverb',
                key: @json($reverb['key']),
                // Same host as the page when REVERB_HOST is the internal bind address.
                wsHost: @json($reverb['options']['host'] ?? null) || window.location.hostname,
                wsPort: port,
                wssPort: port,
                forceTLS: scheme === 'https',
                enabledTransports: ['ws', 'wss'],
                authEndpoint: @json(url('/broadcasting/auth')),
                auth: { headers: { 'X-CSRF-TOKEN': @json(csrf_token()) } }
            });
            window.dispatchEvent(new Event('echo:ready'));
        });
    </script>
@endif
