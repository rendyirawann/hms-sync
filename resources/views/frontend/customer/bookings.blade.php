@extends('frontend.layout')
@section('title', 'Reservasi Saya - ' . $hotel->name)

@php
    $statusStyle = [
        'Pending' => 'bg-yellow-50 text-yellow-700',
        'Paid' => 'bg-sky-50 text-sky-700',
        'Confirmed' => 'bg-blue-50 text-blue-700',
        'Completed' => 'bg-green-50 text-green-700',
        'Cancelled' => 'bg-red-50 text-red-600',
        'Expired' => 'bg-slate-100 text-slate-600',
        'NoShow' => 'bg-slate-100 text-slate-600',
    ];
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <h1 class="text-2xl font-extrabold text-slate-800">Reservasi Saya</h1>
        <a href="{{ route('landing') }}#cari" class="bg-blue-600 text-white px-6 py-3 rounded-xl text-sm font-black hover:bg-blue-700">+ Reservasi baru</a>
    </div>

    <div class="flex flex-col gap-5">
        @forelse ($bookings as $booking)
            @php $res = $booking->reservation; @endphp
            <div class="bg-white rounded-3xl border border-slate-100 shadow-lg shadow-slate-200/30 p-6 flex flex-col md:flex-row gap-6">
                <img loading="lazy" decoding="async" src="{{ $booking->roomType->image_url }}" alt="" class="md:w-40 h-32 rounded-2xl object-cover">
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-3 mb-1">
                        <h3 class="text-lg font-extrabold text-slate-800">{{ $booking->roomType->name }}</h3>
                        <span class="text-xs font-bold px-3 py-1 rounded-full {{ $statusStyle[$booking->status] ?? 'bg-slate-100' }}">
                            {{ $res?->status === 'Active' ? 'Sedang menginap' : $booking->status_label }}
                        </span>
                    </div>
                    <p class="text-xs font-bold text-slate-400 mb-3">Kode: {{ $booking->booking_code }}</p>
                    <p class="text-sm text-slate-600">
                        {{ $booking->check_in_date->translatedFormat('d M Y') }} &ndash; {{ $booking->check_out_date->translatedFormat('d M Y') }}
                        &middot; {{ $booking->nights }} malam &middot; {{ $booking->guests }} tamu
                    </p>
                    @if ($res?->status === 'Active')
                        <div class="mt-4 inline-flex items-center gap-4 rounded-2xl bg-blue-50 px-5 py-3">
                            <div><p class="text-[10px] font-extrabold text-blue-400 uppercase">Kamar</p><p class="text-lg font-black text-blue-800">{{ $res->room->room_number }}</p></div>
                            <div><p class="text-[10px] font-extrabold text-blue-400 uppercase">Passcode pintu</p><p class="text-lg font-black text-blue-800 tracking-[0.3em]">{{ $res->door_passcode }}</p></div>
                        </div>
                    @endif
                    @if ($booking->cancel_reason)
                        <p class="text-xs text-red-500 mt-2">{{ $booking->cancel_reason }}</p>
                    @endif
                </div>
                <div class="md:text-right flex flex-col md:items-end justify-between gap-3">
                    <p class="text-xl font-black text-orange-500">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</p>
                    <a href="{{ route('customer.bookings.show', $booking) }}" class="rounded-xl px-5 py-2.5 text-sm font-black text-center {{ $booking->status === 'Pending' ? 'bg-orange-500 text-white' : 'border border-slate-200 text-slate-700' }}">
                        @if ($booking->status === 'Pending') Bayar sekarang
                        @elseif ($booking->status === 'Completed' && ! $booking->review) Beri ulasan
                        @else Lihat detail @endif
                    </a>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-3xl border border-slate-100 p-16 text-center">
                <p class="text-slate-500 mb-6">Anda belum punya reservasi.</p>
                <a href="{{ route('landing') }}#cari" class="bg-blue-600 text-white px-6 py-3 rounded-xl text-sm font-black">Cari kamar</a>
            </div>
        @endforelse
    </div>

    <div class="mt-8">{{ $bookings->links() }}</div>
@endsection
