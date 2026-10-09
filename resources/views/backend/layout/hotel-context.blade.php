{{-- Which hotel this panel is working on, and its marketplace status. --}}
@php $ctx = \App\Models\Hotel::current(); @endphp

@if ($ctx || auth()->user()?->hasRole('Superadmin'))
    <div class="app-container container-xxl pt-4">
        @if ($ctx && auth()->user()->hasRole('Superadmin'))
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 rounded border border-dashed border-primary bg-light-primary px-5 py-3">
                <div class="fw-semibold text-gray-800">
                    <i class="ki-outline ki-bank fs-4 text-primary me-1"></i>
                    Mengelola hotel <span class="fw-bold">{{ $ctx->name }}</span>
                    <span class="text-muted">· {{ $ctx->city }}</span>
                    <span class="badge badge-light-{{ $ctx->approval_badge }} ms-2">{{ $ctx->approval_label }}</span>
                </div>
                <form method="POST" action="{{ route('platform.hotels.leave') }}" data-no-spinner>
                    @csrf
                    <button type="submit" class="btn btn-sm btn-light">Kembali ke Platform</button>
                </form>
            </div>
        @elseif ($ctx && $ctx->approval_status === 'pending')
            <div class="rounded border border-dashed border-warning bg-light-warning px-5 py-3 fw-semibold text-gray-800">
                <i class="ki-outline ki-time fs-4 text-warning me-1"></i>
                <b>{{ $ctx->name }}</b> sedang ditinjau tim platform. Sambil menunggu, lengkapi profil hotel, tipe kamar, kamar, dan foto.
                Hotel tampil di website setelah disetujui.
            </div>
        @elseif ($ctx && $ctx->approval_status === 'rejected')
            <div class="rounded border border-dashed border-danger bg-light-danger px-5 py-3 fw-semibold text-gray-800">
                Pendaftaran hotel belum disetujui: {{ $ctx->rejection_reason ?: '-' }}. Hubungi tim platform untuk informasi lebih lanjut.
            </div>
        @elseif ($ctx && ! $ctx->status)
            <div class="rounded border border-dashed border-secondary bg-light px-5 py-3 fw-semibold text-gray-800">
                Hotel sedang dinonaktifkan oleh platform dan tidak tampil di website.
            </div>
        @endif
    </div>
@endif
