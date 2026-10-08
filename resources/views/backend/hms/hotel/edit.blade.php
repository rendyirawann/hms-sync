@extends('backend.layout.app')
@section('title', 'Profil Hotel')

@section('content')
    <x-hms.toolbar title="Profil Hotel" section="Data Master">
        <a href="{{ route('landing') }}" target="_blank" class="btn btn-sm btn-light"><i class="ki-outline ki-exit-right-corner fs-4"></i> Lihat website</a>
    </x-hms.toolbar>

    <x-hms.content>
        <form method="POST" action="{{ route('hms.hotel.update') }}" enctype="multipart/form-data" data-ajax>
            @csrf
            @method('PUT')
            <div class="row g-6">
                <div class="col-lg-4">
                    <div class="card card-flush h-100">
                        <div class="card-header"><h3 class="card-title">Foto utama</h3></div>
                        <div class="card-body">
                            <img src="{{ $hotel->image_url ?? asset('assets/media/landing/hero-landing.png') }}" alt="" class="w-100 rounded mb-5 object-fit-cover" style="max-height: 220px;">
                            <input type="file" name="image" accept="image/*" class="form-control form-control-solid">
                            <div class="form-text">Dipakai sebagai gambar hero di website. Maks. 2 MB.</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card card-flush">
                        <div class="card-header"><h3 class="card-title">Informasi hotel</h3></div>
                        <div class="card-body d-flex flex-column gap-5">
                            <div>
                                <label class="form-label required">Nama hotel</label>
                                <input type="text" name="name" value="{{ $hotel->name }}" class="form-control form-control-solid">
                            </div>
                            <div>
                                <label class="form-label required">Alamat</label>
                                <textarea name="address" rows="2" class="form-control form-control-solid">{{ $hotel->address }}</textarea>
                            </div>
                            <div class="row g-5">
                                <div class="col-md-6">
                                    <label class="form-label">Telepon</label>
                                    <input type="text" name="phone" value="{{ $hotel->phone }}" class="form-control form-control-solid">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" value="{{ $hotel->email }}" class="form-control form-control-solid">
                                </div>
                            </div>
                            <div class="row g-5">
                                <div class="col-md-4">
                                    <label class="form-label">Rating (0-5)</label>
                                    <input type="number" step="0.1" min="0" max="5" name="rating" value="{{ $hotel->rating }}" class="form-control form-control-solid">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required">Jam check-in</label>
                                    <input type="time" name="check_in_time" value="{{ substr($hotel->check_in_time, 0, 5) }}" class="form-control form-control-solid">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required">Jam check-out</label>
                                    <input type="time" name="check_out_time" value="{{ substr($hotel->check_out_time, 0, 5) }}" class="form-control form-control-solid">
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Deskripsi</label>
                                <textarea name="description" rows="5" class="form-control form-control-solid">{{ $hotel->description }}</textarea>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary" data-loading-text="Menyimpan..."><i class="ki-outline ki-check fs-3"></i> Simpan</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </x-hms.content>
@endsection
