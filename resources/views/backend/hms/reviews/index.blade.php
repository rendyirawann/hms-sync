@extends('backend.layout.app')
@section('title', 'Ulasan Tamu')

@section('content')
    <x-hms.toolbar title="Ulasan Tamu" section="Data Master" />

    <x-hms.content>
        <div class="card card-flush">
            <div class="card-header py-5">
                <div class="card-title">
                    <span class="fs-2hx fw-bold text-warning me-3">{{ number_format((float) $stats->average, 1) }}</span>
                    <span class="text-muted">dari {{ $stats->total }} ulasan</span>
                </div>
                <div class="card-toolbar">
                    <form method="GET" data-no-spinner>
                        <select name="rating" class="form-select form-select-solid w-150px" onchange="this.form.submit()">
                            <option value="">Semua rating</option>
                            @for ($i = 5; $i >= 1; $i--)
                                <option value="{{ $i }}" @selected(request('rating') == $i)>{{ $i }} bintang</option>
                            @endfor
                        </select>
                    </form>
                </div>
            </div>
            <div class="card-body pt-0">
                @forelse ($reviews as $review)
                    <div class="d-flex flex-wrap justify-content-between gap-4 border-bottom border-dashed py-5">
                        <div class="flex-grow-1 mw-700px">
                            <div class="d-flex align-items-center gap-3 mb-1">
                                <span class="fw-bold text-gray-900">{{ $review->user->name ?? 'Tamu' }}</span>
                                <span class="text-warning">{{ str_repeat('★', $review->rating) }}<span class="text-gray-300">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                            </div>
                            <div class="text-muted fs-7 mb-2">{{ $review->roomType->name ?? '-' }} &middot; {{ $review->booking->booking_code ?? '' }} &middot; {{ $review->created_at->translatedFormat('d M Y') }}</div>
                            <div class="text-gray-700">{{ $review->comment ?: '(tanpa komentar)' }}</div>
                        </div>
                        <form method="POST" action="{{ route('hms.reviews.toggle', $review) }}" data-ajax>
                            @csrf
                            <button type="submit" class="btn btn-sm {{ $review->is_published ? 'btn-light-success' : 'btn-light' }}">
                                {{ $review->is_published ? 'Tampil di website' : 'Disembunyikan' }}
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="text-center text-muted py-10">Belum ada ulasan.</div>
                @endforelse
                <div class="mt-5">{{ $reviews->links('pagination::bootstrap-5') }}</div>
            </div>
        </div>
    </x-hms.content>
@endsection
