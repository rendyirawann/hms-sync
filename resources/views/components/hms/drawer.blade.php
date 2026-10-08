{{-- Right-side form drawer (WORKFLOW.md §4: add/edit slides in from the right).
     Opened by any [data-hms-drawer="#id"] trigger; see hms-admin.js. --}}
@props(['id', 'title', 'action', 'files' => false, 'width' => '480px'])

<div class="offcanvas offcanvas-end" tabindex="-1" id="{{ $id }}" style="width: min(100vw, {{ $width }});" aria-labelledby="{{ $id }}-title">
    <form method="POST" action="{{ $action }}" data-default-action="{{ $action }}" data-ajax
        class="d-flex flex-column h-100" @if ($files) enctype="multipart/form-data" @endif novalidate>
        @csrf
        <input type="hidden" name="_method" value="POST">
        <div class="offcanvas-header border-bottom">
            <h3 class="offcanvas-title fw-bold" id="{{ $id }}-title" data-drawer-title>{{ $title }}</h3>
            <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="offcanvas" aria-label="Tutup">
                <i class="ki-outline ki-cross fs-1"></i>
            </button>
        </div>
        <div class="offcanvas-body d-flex flex-column gap-5">
            {{ $slot }}
        </div>
        <div class="offcanvas-footer border-top p-5 d-flex justify-content-end gap-3">
            <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Batal</button>
            <button type="submit" class="btn btn-primary" data-loading-text="Menyimpan...">
                <i class="ki-outline ki-check fs-3"></i> Simpan
            </button>
        </div>
    </form>
</div>
