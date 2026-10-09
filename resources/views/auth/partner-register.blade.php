@extends('auth.app')

@section('title', 'Daftarkan Hotel')

@section('content')
    <div class="auth-card">
        <div class="auth-card__brand">
            <img src="{{ $brand['logo_url'] }}" alt="" class="hms-logo" width="56" height="56" />
        </div>

        <h1 class="auth-card__title">Daftarkan hotel Anda</h1>
        <p class="auth-card__subtitle">Jual kamar di {{ $brand['name'] }}. Kami tinjau data hotel sebelum tayang di website.</p>

        @if ($errors->any())
            <div class="auth-alert" role="alert">
                <i class="ki-outline ki-shield-cross fs-4 mt-1" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('partner.register') }}">
            @csrf

            <div class="auth-label mb-2" style="opacity:.75">Data hotel</div>
            <div class="auth-field">
                <label class="auth-label" for="p-hotel">Nama hotel</label>
                <div class="auth-input-wrap"><input type="text" name="hotel_name" id="p-hotel" class="auth-input" value="{{ old('hotel_name') }}" required autofocus /></div>
            </div>
            <div class="auth-field">
                <label class="auth-label" for="p-city">Kota</label>
                <div class="auth-input-wrap"><input type="text" name="city" id="p-city" class="auth-input" value="{{ old('city') }}" required /></div>
            </div>
            <div class="auth-field">
                <label class="auth-label" for="p-address">Alamat lengkap</label>
                <div class="auth-input-wrap"><input type="text" name="address" id="p-address" class="auth-input" value="{{ old('address') }}" required /></div>
            </div>
            <div class="auth-field">
                <label class="auth-label" for="p-phone">Telepon hotel</label>
                <div class="auth-input-wrap"><input type="tel" name="hotel_phone" id="p-phone" class="auth-input" value="{{ old('hotel_phone') }}" required /></div>
            </div>

            <div class="auth-label mb-2 mt-4" style="opacity:.75">Akun pemilik / pengelola</div>
            <div class="auth-field">
                <label class="auth-label" for="p-name">Nama lengkap</label>
                <div class="auth-input-wrap"><input type="text" name="name" id="p-name" class="auth-input" value="{{ old('name') }}" autocomplete="name" required /></div>
            </div>
            <div class="auth-field">
                <label class="auth-label" for="p-email">Email</label>
                <div class="auth-input-wrap"><input type="email" name="email" id="p-email" class="auth-input" value="{{ old('email') }}" autocomplete="email" required /></div>
            </div>
            <div class="auth-field">
                <label class="auth-label" for="p-password">Kata sandi</label>
                <div class="auth-input-wrap">
                    <input type="password" name="password" id="p-password" class="auth-input auth-input--password" autocomplete="new-password" required />
                    <button type="button" class="auth-toggle" data-auth-toggle-password="p-password" aria-label="Tampilkan password" aria-pressed="false">
                        <i class="ki-outline ki-eye fs-4" data-eye aria-hidden="true"></i>
                        <i class="ki-outline ki-eye-slash fs-4" data-eye-off style="display:none" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="auth-field">
                <label class="auth-label" for="p-password2">Ulangi kata sandi</label>
                <div class="auth-input-wrap"><input type="password" name="password_confirmation" id="p-password2" class="auth-input auth-input--password" autocomplete="new-password" required /></div>
            </div>

            <label class="auth-check mb-4" for="p-agree">
                <input type="checkbox" name="agree" id="p-agree" value="1" @checked(old('agree')) />
                <span>Saya setuju pembayaran online tamu diterima platform dan disetor ke hotel setelah dipotong komisi.</span>
            </label>

            <button type="submit" class="auth-submit hms-btn">Daftarkan hotel</button>
        </form>

        <p class="auth-foot">
            Sudah terdaftar?
            <a href="{{ route('login') }}" class="text-decoration-none" style="color: #a5b4fc;">Masuk ke panel mitra</a>
        </p>
    </div>
@endsection
