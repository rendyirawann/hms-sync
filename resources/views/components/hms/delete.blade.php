@props(['action', 'confirm' => 'Data yang dihapus tidak bisa dikembalikan.', 'label' => null])

<form method="POST" action="{{ $action }}" class="d-inline" data-ajax data-ajax-confirm="{{ $confirm }}"
    data-confirm-title="Hapus data?" data-confirm-ok="Ya, hapus">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-sm btn-icon btn-light-danger" title="Hapus" data-loading-text="">
        <i class="ki-outline ki-trash fs-4"></i>
    </button>
</form>
