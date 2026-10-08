@props(['title', 'section' => 'Hotel'])

<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-0">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack flex-wrap gap-3">
        <div class="page-title d-flex flex-column justify-content-center me-3">
            <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">{{ $title }}</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                <li class="breadcrumb-item text-gray-700 fw-bold lh-1">
                    <a href="{{ route('dashboard') }}" class="text-hover-primary"><i class="ki-outline ki-home text-gray-700 fs-6"></i></a>
                </li>
                <li class="breadcrumb-item"><i class="ki-outline ki-right fs-5 text-gray-700 mx-n1"></i></li>
                <li class="breadcrumb-item text-muted">{{ $section }}</li>
                <li class="breadcrumb-item"><i class="ki-outline ki-right fs-5 text-gray-700 mx-n1"></i></li>
                <li class="breadcrumb-item text-gray-900">{{ $title }}</li>
            </ul>
        </div>
        <div class="d-flex align-items-center gap-2">{{ $slot }}</div>
    </div>
</div>
