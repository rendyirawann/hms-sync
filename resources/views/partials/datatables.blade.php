{{-- DataTables is 2.4 MB: only pages that render a DataTable include this. --}}
@once
    @push('stylesheets')
        <link href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
    @endpush
    @push('vendor-scripts')
        <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
    @endpush
@endonce
