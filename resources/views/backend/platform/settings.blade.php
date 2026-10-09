@extends('backend.layout.app')
@section('title', 'Pengaturan Platform')

@section('content')
    <x-hms.toolbar title="Pengaturan Platform" section="Platform" />

    <x-hms.content>
        <form method="POST" action="{{ route('platform.settings.update') }}" data-ajax class="card card-flush mw-800px">
            @csrf
            @method('PUT')
            <div class="card-body d-flex flex-column gap-6">
                <div>
                    <label class="form-label">Rekening platform untuk transfer tamu</label>
                    <textarea name="bank_accounts" rows="4" class="form-control form-control-solid" placeholder="BCA 1234567890 a.n. PT Platform&#10;Mandiri 0987654321 a.n. PT Platform">{{ $bankAccounts }}</textarea>
                    <div class="form-text">Ditampilkan ke tamu yang memilih transfer manual. Kosongkan untuk menonaktifkan transfer manual (hanya Tripay).</div>
                </div>
                <div class="w-200px">
                    <label class="form-label required">Komisi default hotel baru (%)</label>
                    <input type="number" name="default_commission" step="0.5" min="0" max="100" value="{{ $defaultCommission }}" class="form-control form-control-solid">
                </div>
                <label class="form-check form-switch form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" name="auto_approve" value="1" @checked($autoApprove)>
                    <span class="form-check-label">Setujui otomatis hotel yang mendaftar sendiri</span>
                </label>
            </div>
            <div class="card-footer d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </x-hms.content>
@endsection
