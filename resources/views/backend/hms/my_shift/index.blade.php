@extends('backend.layout.app')
@section('title', 'Shift Saya')

@section('content')
    <x-hms.toolbar title="Shift Saya" section="Shift" />

    <x-hms.content>
        <div class="row g-6">
            <div class="col-lg-5">
                <div class="card card-flush h-100">
                    <div class="card-body text-center py-10">
                        <div class="text-muted fw-semibold mb-2">{{ now()->translatedFormat('l, d F Y') }}</div>
                        <div class="fs-2hx fw-bold text-gray-900 mb-6" id="clock">{{ now()->format('H:i') }}</div>

                        @php $current = $running ?? $today; @endphp

                        @if (! $current)
                            <i class="ki-outline ki-coffee fs-5x text-gray-300 mb-4"></i>
                            <div class="fs-5 text-gray-600">Tidak ada jadwal shift hari ini.</div>
                        @else
                            <div class="fs-4 fw-bold mb-1">{{ $current->shift->label }}</div>
                            <span class="badge badge-light-{{ $current->status_badge }} mb-6">{{ ['Pending' => 'Belum mulai', 'Started' => 'Sedang bertugas', 'Finished' => 'Selesai'][$current->status] }}</span>

                            @if ($current->status === 'Pending')
                                <form method="POST" action="{{ route('hms.my-shift.start', $current) }}" data-ajax>
                                    @csrf
                                    <button type="submit" class="btn btn-lg btn-success w-100" data-loading-text="Memulai...">
                                        <i class="ki-outline ki-entrance-left fs-2"></i> Mulai Shift (Clock-in)
                                    </button>
                                </form>
                            @elseif ($current->status === 'Started')
                                <div class="text-muted mb-4">Mulai pukul {{ $current->start_at->format('H:i') }}</div>
                                <form method="POST" action="{{ route('hms.my-shift.finish', $current) }}" data-ajax data-ajax-confirm="Akhiri shift sekarang?" data-confirm-icon="question" data-confirm-ok="Ya, akhiri">
                                    @csrf
                                    <button type="submit" class="btn btn-lg btn-danger w-100" data-loading-text="Mengakhiri...">
                                        <i class="ki-outline ki-exit-right fs-2"></i> Akhiri Shift (Clock-out)
                                    </button>
                                </form>
                            @else
                                <div class="text-muted">{{ $current->start_at?->format('H:i') }} - {{ $current->end_at?->format('H:i') }}</div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card card-flush h-100">
                    <div class="card-header"><h3 class="card-title">Jadwal &amp; riwayat</h3></div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-3">
                                <thead>
                                    <tr class="text-gray-500 fw-bold fs-7 text-uppercase">
                                        <th>Tanggal</th><th>Shift</th><th>Masuk</th><th>Keluar</th><th>Status</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-700">
                                    @forelse ($history as $entry)
                                        <tr class="{{ $entry->date->isToday() ? 'bg-light-primary' : '' }}">
                                            <td>{{ $entry->date->translatedFormat('D, d M') }}</td>
                                            <td>{{ $entry->shift->label }}</td>
                                            <td>{{ $entry->start_at?->format('H:i') ?? '-' }}</td>
                                            <td>{{ $entry->end_at?->format('H:i') ?? '-' }}</td>
                                            <td><span class="badge badge-light-{{ $entry->status_badge }}">{{ $entry->status }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-8">Belum ada jadwal.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-hms.content>
@endsection

@push('scripts')
    <script>
        setInterval(function () {
            var d = new Date();
            document.getElementById('clock').textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        }, 10000);
    </script>
@endpush
