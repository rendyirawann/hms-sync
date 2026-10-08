@extends('backend.layout.app')
@section('title', 'Harga & Promo')

@php
    $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <x-hms.toolbar title="Harga & Promo" section="Data Master" />

    <x-hms.content>
        <div class="card card-flush mb-6">
            <div class="card-header py-5">
                <div class="card-title flex-column">
                    <h3 class="mb-1">Aturan harga</h3>
                    <span class="text-muted fs-7">Harga weekend, musim liburan, atau tanggal khusus. Jika beberapa aturan cocok, prioritas tertinggi yang dipakai.</span>
                </div>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-sm btn-primary" data-hms-drawer="#drawer-rule" data-title="Tambah Aturan Harga"
                        data-action="{{ route('hms.rate-rules.store') }}" data-method="POST">
                        <i class="ki-outline ki-plus fs-3"></i> Tambah aturan
                    </button>
                </div>
            </div>
            <div class="card-body pt-0 table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-3">
                    <thead>
                        <tr class="text-gray-500 fw-bold fs-7 text-uppercase">
                            <th>Nama</th><th>Tipe kamar</th><th>Periode</th><th>Hari</th><th>Penyesuaian</th><th>Prioritas</th><th>Status</th><th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                        @forelse ($rules as $rule)
                            <tr>
                                <td class="text-gray-900 fw-bold">{{ $rule->name }}</td>
                                <td>{{ $rule->roomType->name ?? 'Semua tipe' }}</td>
                                <td>{{ $rule->start_date?->format('d/m/Y') ?? '...' }} - {{ $rule->end_date?->format('d/m/Y') ?? '...' }}</td>
                                <td>{{ $rule->days_of_week ? collect($rule->days_of_week)->map(fn ($d) => $dayNames[$d])->implode(', ') : 'Setiap hari' }}</td>
                                <td>
                                    @if ($rule->adjust_type === 'percent') {{ $rule->value > 0 ? '+' : '' }}{{ (float) $rule->value }}%
                                    @elseif ($rule->adjust_type === 'amount') {{ $rule->value > 0 ? '+' : '-' }}{{ $rp(abs($rule->value)) }}
                                    @else = {{ $rp($rule->value) }} @endif
                                </td>
                                <td>{{ $rule->priority }}</td>
                                <td><span class="badge badge-light-{{ $rule->is_active ? 'success' : 'secondary' }}">{{ $rule->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-icon btn-light-primary" data-hms-drawer="#drawer-rule" data-title="Ubah Aturan Harga"
                                        data-action="{{ route('hms.rate-rules.update', $rule) }}" data-method="PUT"
                                        data-record="{{ json_encode([
                                            'name' => $rule->name, 'room_type_id' => $rule->room_type_id,
                                            'start_date' => $rule->start_date?->toDateString(), 'end_date' => $rule->end_date?->toDateString(),
                                            'days_of_week' => array_map('strval', $rule->days_of_week ?? []), 'adjust_type' => $rule->adjust_type,
                                            'value' => (float) $rule->value, 'priority' => $rule->priority, 'is_active' => $rule->is_active,
                                        ]) }}">
                                        <i class="ki-outline ki-pencil fs-4"></i>
                                    </button>
                                    <x-hms.delete :action="route('hms.rate-rules.destroy', $rule)" />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-8">Belum ada aturan. Contoh: "Weekend +20%" untuk hari Jumat &amp; Sabtu.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-flush">
            <div class="card-header py-5">
                <div class="card-title flex-column">
                    <h3 class="mb-1">Kode promo</h3>
                    <span class="text-muted fs-7">Diskon dari harga kamar, dipakai tamu saat memesan.</span>
                </div>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-sm btn-primary" data-hms-drawer="#drawer-promo" data-title="Tambah Kode Promo"
                        data-action="{{ route('hms.promo-codes.store') }}" data-method="POST">
                        <i class="ki-outline ki-plus fs-3"></i> Tambah promo
                    </button>
                </div>
            </div>
            <div class="card-body pt-0 table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-3">
                    <thead>
                        <tr class="text-gray-500 fw-bold fs-7 text-uppercase">
                            <th>Kode</th><th>Diskon</th><th>Berlaku</th><th>Min. malam</th><th>Terpakai</th><th>Status</th><th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                        @forelse ($promos as $promo)
                            <tr>
                                <td class="text-gray-900 fw-bolder font-monospace">{{ $promo->code }}</td>
                                <td>
                                    {{ $promo->type === 'percent' ? (float) $promo->value . '%' : $rp($promo->value) }}
                                    @if ($promo->max_discount) <div class="text-muted fs-8">maks. {{ $rp($promo->max_discount) }}</div> @endif
                                </td>
                                <td>{{ $promo->valid_from?->format('d/m/Y') ?? '...' }} - {{ $promo->valid_until?->format('d/m/Y') ?? '...' }}</td>
                                <td>{{ $promo->min_nights }}</td>
                                <td>{{ $promo->used_count }}{{ $promo->max_uses ? ' / ' . $promo->max_uses : '' }}</td>
                                <td><span class="badge badge-light-{{ $promo->is_active ? 'success' : 'secondary' }}">{{ $promo->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-icon btn-light-primary" data-hms-drawer="#drawer-promo" data-title="Ubah Kode Promo"
                                        data-action="{{ route('hms.promo-codes.update', $promo) }}" data-method="PUT"
                                        data-record="{{ json_encode([
                                            'code' => $promo->code, 'type' => $promo->type, 'value' => (float) $promo->value,
                                            'max_discount' => $promo->max_discount ? (float) $promo->max_discount : null, 'min_nights' => $promo->min_nights,
                                            'valid_from' => $promo->valid_from?->toDateString(), 'valid_until' => $promo->valid_until?->toDateString(),
                                            'max_uses' => $promo->max_uses, 'is_active' => $promo->is_active,
                                        ]) }}">
                                        <i class="ki-outline ki-pencil fs-4"></i>
                                    </button>
                                    <x-hms.delete :action="route('hms.promo-codes.destroy', $promo)" confirm="Kode yang sudah dipakai akan dinonaktifkan, bukan dihapus." />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-8">Belum ada kode promo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-hms.content>

    <x-hms.drawer id="drawer-rule" title="Tambah Aturan Harga" :action="route('hms.rate-rules.store')">
        <div>
            <label class="form-label required">Nama aturan</label>
            <input type="text" name="name" class="form-control form-control-solid" placeholder="mis. Weekend, Lebaran 2027">
        </div>
        <div>
            <label class="form-label">Tipe kamar</label>
            <select name="room_type_id" class="form-select form-select-solid">
                <option value="">Semua tipe kamar</option>
                @foreach ($roomTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="row g-5">
            <div class="col-6">
                <label class="form-label">Mulai tanggal</label>
                <input type="date" name="start_date" class="form-control form-control-solid">
            </div>
            <div class="col-6">
                <label class="form-label">Sampai tanggal</label>
                <input type="date" name="end_date" class="form-control form-control-solid">
            </div>
        </div>
        <div>
            <label class="form-label">Hanya hari</label>
            <select name="days_of_week[]" multiple class="form-select form-select-solid" size="7">
                @foreach ($dayNames as $i => $day)
                    <option value="{{ $i }}">{{ $day }}</option>
                @endforeach
            </select>
            <div class="form-text">Ctrl/Cmd+klik untuk memilih beberapa. Kosongkan = setiap hari.</div>
        </div>
        <div class="row g-5">
            <div class="col-6">
                <label class="form-label required">Jenis</label>
                <select name="adjust_type" class="form-select form-select-solid">
                    @foreach (\App\Models\RateRule::TYPES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6">
                <label class="form-label required">Nilai</label>
                <input type="number" name="value" step="0.01" class="form-control form-control-solid" placeholder="20 / -50000 / 750000">
            </div>
        </div>
        <div>
            <label class="form-label">Prioritas</label>
            <input type="number" name="priority" value="0" min="0" max="1000" class="form-control form-control-solid">
            <div class="form-text">Angka lebih besar menang jika ada aturan yang tumpang tindih.</div>
        </div>
        <label class="form-check form-switch form-check-custom form-check-solid">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
            <span class="form-check-label">Aktif</span>
        </label>
    </x-hms.drawer>

    <x-hms.drawer id="drawer-promo" title="Tambah Kode Promo" :action="route('hms.promo-codes.store')">
        <div>
            <label class="form-label required">Kode</label>
            <input type="text" name="code" class="form-control form-control-solid text-uppercase" placeholder="mis. HEMAT20">
        </div>
        <div class="row g-5">
            <div class="col-6">
                <label class="form-label required">Jenis</label>
                <select name="type" class="form-select form-select-solid">
                    <option value="percent">Persen (%)</option>
                    <option value="amount">Potongan (Rp)</option>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label required">Nilai</label>
                <input type="number" name="value" min="0" step="0.01" class="form-control form-control-solid">
            </div>
        </div>
        <div class="row g-5">
            <div class="col-6">
                <label class="form-label">Maks. diskon (Rp)</label>
                <input type="number" name="max_discount" min="0" class="form-control form-control-solid">
            </div>
            <div class="col-6">
                <label class="form-label">Minimal malam</label>
                <input type="number" name="min_nights" value="1" min="1" class="form-control form-control-solid">
            </div>
        </div>
        <div class="row g-5">
            <div class="col-6">
                <label class="form-label">Berlaku dari</label>
                <input type="date" name="valid_from" class="form-control form-control-solid">
            </div>
            <div class="col-6">
                <label class="form-label">Berlaku sampai</label>
                <input type="date" name="valid_until" class="form-control form-control-solid">
            </div>
        </div>
        <div>
            <label class="form-label">Kuota pemakaian</label>
            <input type="number" name="max_uses" min="1" class="form-control form-control-solid" placeholder="Kosongkan = tanpa batas">
        </div>
        <label class="form-check form-switch form-check-custom form-check-solid">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
            <span class="form-check-label">Aktif</span>
        </label>
    </x-hms.drawer>
@endsection
