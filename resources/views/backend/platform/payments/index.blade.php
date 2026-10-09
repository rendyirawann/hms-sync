@extends('backend.layout.app')
@section('title', 'Verifikasi Transfer')

@php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp

@section('content')
    <x-hms.toolbar title="Verifikasi Transfer" section="Platform" />

    <x-hms.content>
        <div class="card card-flush">
            <div class="card-header py-5">
                <div class="card-title flex-column">
                    <h3 class="mb-1">Bukti transfer tamu</h3>
                    <span class="text-muted fs-7">Transfer masuk ke rekening platform. Cocokkan nominal dengan mutasi rekening sebelum menerima. {{ $pending }} menunggu.</span>
                </div>
                <div class="card-toolbar">
                    <form method="GET" data-no-spinner>
                        <select name="status" class="form-select form-select-solid w-175px" onchange="this.form.submit()">
                            @foreach (['pending' => 'Menunggu', 'paid' => 'Diterima', 'rejected' => 'Ditolak', 'all' => 'Semua'] as $key => $label)
                                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
            <div class="card-body pt-0 table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-4">
                    <thead>
                        <tr class="text-gray-500 fw-bold fs-7 text-uppercase"><th>Diunggah</th><th>Booking</th><th>Hotel</th><th>Tamu</th><th class="text-end">Nominal</th><th>Status</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                        @forelse ($payments as $payment)
                            <tr>
                                <td>{{ $payment->created_at->format('d/m/Y H:i') }}</td>
                                <td class="fw-bold text-gray-900">{{ $payment->booking?->booking_code }}</td>
                                <td>{{ $payment->hotel?->name }}</td>
                                <td>{{ $payment->booking?->user?->name ?? $payment->booking?->guest_name }}</td>
                                <td class="text-end">{{ $rp($payment->amount) }}</td>
                                <td>
                                    <span class="badge badge-light-{{ $payment->status_badge }}">{{ $payment->status_label }}</span>
                                    @if ($payment->verifier)<div class="text-muted fs-8">oleh {{ $payment->verifier->name }}</div>@endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($payment->proof_path)
                                        <a href="{{ route('platform.payments.proof', $payment) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light">Bukti</a>
                                    @endif
                                    @if ($payment->status === 'pending')
                                        <form method="POST" action="{{ route('platform.payments.verify', $payment) }}" class="d-inline" data-ajax data-ajax-confirm="Nominal {{ $rp($payment->amount) }} sudah masuk ke rekening platform?" data-confirm-icon="question" data-confirm-ok="Ya, terima">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">Terima</button>
                                        </form>
                                        <form method="POST" action="{{ route('platform.payments.reject', $payment) }}" class="d-inline" data-ajax data-ajax-confirm="Tolak bukti transfer ini?">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-light-danger">Tolak</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-10">Tidak ada bukti transfer.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $payments->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </x-hms.content>
@endsection
