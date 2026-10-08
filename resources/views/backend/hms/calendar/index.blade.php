@extends('backend.layout.app')
@section('title', 'Kalender Okupansi')

@push('stylesheets')
    <style>
        .occ-grid th, .occ-grid td { min-width: 56px; text-align: center; padding: .4rem .25rem !important; }
        .occ-grid .sticky-col { position: sticky; left: 0; background: var(--bs-body-bg); z-index: 1; min-width: 130px; text-align: left; }
        .occ-cell { display: block; border-radius: .35rem; font-size: .7rem; line-height: 1.2; padding: .3rem .2rem; color: #fff; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
    </style>
@endpush

@section('content')
    <x-hms.toolbar title="Kalender Okupansi" section="Front Desk">
        <a href="{{ route('hms.bookings.calendar', ['start' => $start->copy()->subDays(14)->toDateString()]) }}" class="btn btn-sm btn-light"><i class="ki-outline ki-left fs-4"></i></a>
        <a href="{{ route('hms.bookings.calendar') }}" class="btn btn-sm btn-light">Hari ini</a>
        <a href="{{ route('hms.bookings.calendar', ['start' => $start->copy()->addDays(14)->toDateString()]) }}" class="btn btn-sm btn-light"><i class="ki-outline ki-right fs-4"></i></a>
    </x-hms.toolbar>

    <x-hms.content>
        <div class="card card-flush mb-6">
            <div class="card-header"><h3 class="card-title">Sisa kamar per tipe</h3></div>
            <div class="card-body pt-0 table-responsive">
                <table class="table table-bordered align-middle fs-7 occ-grid mb-0">
                    <thead>
                        <tr class="fw-bold text-gray-600 bg-light">
                            <th class="sticky-col">Tipe</th>
                            @foreach ($dates as $date)
                                <th class="{{ $date->isToday() ? 'text-primary' : ($date->isWeekend() ? 'text-danger' : '') }}">{{ $date->translatedFormat('D') }}<br>{{ $date->format('d/m') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($types as $type)
                            <tr>
                                <td class="sticky-col fw-bold">{{ $type->name }}</td>
                                @foreach ($dates as $date)
                                    @php
                                        $total = $sellable[$type->id] ?? 0;
                                        $left = max(0, $total - ($held[$type->id][$date->toDateString()] ?? 0));
                                        $color = $left === 0 ? 'danger' : ($left <= max(1, intdiv($total, 4)) ? 'warning' : 'success');
                                    @endphp
                                    <td><span class="badge badge-light-{{ $color }}">{{ $left }}/{{ $total }}</span></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-flush">
            <div class="card-header">
                <h3 class="card-title">Kamar</h3>
                <div class="card-toolbar fs-8 gap-3">
                    <span><span class="badge bg-primary">&nbsp;</span> Menginap</span>
                    <span><span class="badge bg-success">&nbsp;</span> Sudah check-out</span>
                </div>
            </div>
            <div class="card-body pt-0 table-responsive">
                <table class="table table-bordered align-middle fs-7 occ-grid mb-0">
                    <thead>
                        <tr class="fw-bold text-gray-600 bg-light">
                            <th class="sticky-col">Kamar</th>
                            @foreach ($dates as $date)
                                <th class="{{ $date->isToday() ? 'text-primary' : '' }}">{{ $date->format('d/m') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rooms as $room)
                            <tr>
                                <td class="sticky-col">
                                    <span class="fw-bold">{{ $room->room_number }}</span>
                                    <div class="text-muted fs-8">{{ $room->roomType->name ?? '' }}</div>
                                </td>
                                @foreach ($dates as $date)
                                    @php $stay = $byRoom[$room->id][$date->toDateString()] ?? null; @endphp
                                    <td>
                                        @if ($stay)
                                            <a href="#" data-hms-show="{{ route('hms.bookings.show', $stay) }}" data-title="Reservasi {{ $stay->booking_code }}"
                                                class="occ-cell bg-{{ $stay->status === 'Completed' ? 'success' : 'primary' }}" title="{{ $stay->guest_display_name }}">
                                                {{ \Illuminate\Support\Str::limit($stay->guest_display_name, 8, '') }}
                                            </a>
                                        @elseif ($room->status === 'Maintenance' && $date->isToday())
                                            <span class="occ-cell bg-secondary text-gray-700">Perbaikan</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </x-hms.content>
@endsection
