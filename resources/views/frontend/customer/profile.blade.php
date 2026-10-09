@extends('frontend.layout')
@section('title', 'Profil Saya - ' . $brand['name'])

@section('content')
    <h1 class="text-2xl font-extrabold text-slate-800 mb-8">Profil Saya</h1>

    <div class="grid lg:grid-cols-2 gap-8">
        <form method="POST" action="{{ route('customer.profile.update') }}" class="bg-white rounded-3xl border border-slate-100 shadow-lg shadow-slate-200/30 p-8 flex flex-col gap-5">
            @csrf
            @method('PUT')
            <h2 class="text-lg font-extrabold text-slate-800">Data diri</h2>
            <label class="block">
                <span class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Nama lengkap</span>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 font-bold text-slate-800">
            </label>
            <label class="block">
                <span class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Email</span>
                <input type="email" value="{{ $user->email }}" disabled class="mt-1 w-full rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 font-bold text-slate-500">
            </label>
            <label class="block">
                <span class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Nomor HP</span>
                <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="15" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 font-bold text-slate-800">
            </label>
            <button type="submit" class="self-start bg-blue-600 text-white px-6 py-3 rounded-xl text-sm font-black">Simpan</button>
        </form>

        @if (! $user->social_type)
            <form method="POST" action="{{ route('customer.profile.password') }}" class="bg-white rounded-3xl border border-slate-100 shadow-lg shadow-slate-200/30 p-8 flex flex-col gap-5">
                @csrf
                @method('PUT')
                <h2 class="text-lg font-extrabold text-slate-800">Ganti password</h2>
                <label class="block">
                    <span class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Password lama</span>
                    <input type="password" name="current_password" required autocomplete="current-password" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-800">
                </label>
                <label class="block">
                    <span class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Password baru</span>
                    <input type="password" name="password" required autocomplete="new-password" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-800">
                </label>
                <label class="block">
                    <span class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Ulangi password baru</span>
                    <input type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-800">
                </label>
                <button type="submit" class="self-start bg-slate-800 text-white px-6 py-3 rounded-xl text-sm font-black">Ganti password</button>
            </form>
        @endif
    </div>
@endsection
