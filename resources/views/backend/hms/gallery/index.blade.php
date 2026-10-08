@extends('backend.layout.app')
@section('title', 'Galeri Foto')

@section('content')
    <x-hms.toolbar title="Galeri Foto" section="Data Master" />

    <x-hms.content>
        <div class="card card-flush mb-6">
            <div class="card-header py-5">
                <div class="card-title flex-column">
                    <h3 class="mb-1">Fasilitas hotel</h3>
                    <span class="text-muted fs-7">Tampil di halaman depan website.</span>
                </div>
            </div>
            <div class="card-body pt-0">
                <form method="POST" action="{{ route('hms.gallery.hotel.store') }}" enctype="multipart/form-data" data-ajax class="d-flex flex-wrap gap-3 mb-6">
                    @csrf
                    <input type="file" name="photos[]" accept="image/*" multiple class="form-control form-control-solid w-auto">
                    <input type="text" name="caption" class="form-control form-control-solid w-250px" placeholder="Keterangan (mis. Kolam renang)">
                    <button type="submit" class="btn btn-primary" data-loading-text="Mengunggah..."><i class="ki-outline ki-cloud-add fs-3"></i> Unggah</button>
                </form>
                <div class="d-flex flex-wrap gap-4">
                    @forelse ($hotelImages as $image)
                        <div class="position-relative">
                            <img src="{{ $image->url }}" alt="" loading="lazy" class="rounded w-150px h-100px object-fit-cover">
                            <div class="fs-8 text-muted mt-1 w-150px text-truncate">{{ $image->caption }}</div>
                            <form method="POST" action="{{ route('hms.gallery.hotel.destroy', $image) }}" data-ajax data-ajax-confirm="Hapus foto ini?" class="position-absolute top-0 end-0 m-1">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-icon btn-sm btn-danger w-25px h-25px" data-loading-text=""><i class="ki-outline ki-cross fs-6"></i></button>
                            </form>
                        </div>
                    @empty
                        <span class="text-muted">Belum ada foto fasilitas.</span>
                    @endforelse
                </div>
            </div>
        </div>

        @foreach ($roomTypes as $type)
            <div class="card card-flush mb-6">
                <div class="card-header py-5">
                    <h3 class="card-title">{{ $type->name }}</h3>
                </div>
                <div class="card-body pt-0">
                    <form method="POST" action="{{ route('hms.gallery.room-type.store', $type) }}" enctype="multipart/form-data" data-ajax class="d-flex flex-wrap gap-3 mb-6">
                        @csrf
                        <input type="file" name="photos[]" accept="image/*" multiple class="form-control form-control-solid w-auto">
                        <button type="submit" class="btn btn-light-primary" data-loading-text="Mengunggah..."><i class="ki-outline ki-cloud-add fs-3"></i> Unggah foto</button>
                    </form>
                    <div class="d-flex flex-wrap gap-4">
                        @if ($type->image)
                            <div>
                                <img src="{{ $type->image_url }}" alt="" loading="lazy" class="rounded w-150px h-100px object-fit-cover border border-primary border-2">
                                <div class="fs-8 text-primary mt-1">Foto utama (ubah di Tipe Kamar)</div>
                            </div>
                        @endif
                        @foreach ($type->images as $image)
                            <div class="position-relative">
                                <img src="{{ $image->url }}" alt="" loading="lazy" class="rounded w-150px h-100px object-fit-cover">
                                <form method="POST" action="{{ route('hms.gallery.room-type.destroy', $image) }}" data-ajax data-ajax-confirm="Hapus foto ini?" class="position-absolute top-0 end-0 m-1">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-icon btn-sm btn-danger w-25px h-25px" data-loading-text=""><i class="ki-outline ki-cross fs-6"></i></button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </x-hms.content>
@endsection
