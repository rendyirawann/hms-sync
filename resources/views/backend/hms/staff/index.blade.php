@extends('backend.layout.app')
@section('title', 'Pegawai')

@section('content')
    <x-hms.toolbar title="Pegawai" section="Data Master">
        <button type="button" class="btn btn-sm btn-primary" data-hms-drawer="#drawer-staff"
            data-title="Tambah Pegawai" data-action="{{ route('hms.staff.store') }}" data-method="POST">
            <i class="ki-outline ki-plus fs-3"></i> Tambah Pegawai
        </button>
    </x-hms.toolbar>

    <x-hms.content>
        <div class="card card-flush">
            <div class="card-header py-5">
                <form method="GET" class="card-title" data-no-spinner>
                    <div class="d-flex align-items-center position-relative">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-solid w-250px ps-12" placeholder="Nama, email, atau NIK">
                    </div>
                </form>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase">
                                <th>Pegawai</th>
                                <th>NIK</th>
                                <th>Telepon</th>
                                <th>Jabatan</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @forelse ($staff as $person)
                                @php $role = $person->roles->first()?->name; @endphp
                                <tr>
                                    <td>
                                        <div class="text-gray-900 fw-bold">{{ $person->name }}</div>
                                        <div class="text-muted fs-7">{{ $person->email }}</div>
                                    </td>
                                    <td>{{ $person->nik ?: '-' }}</td>
                                    <td>{{ $person->phone ?: '-' }}</td>
                                    <td><span class="badge badge-light-{{ $role === 'admin' ? 'primary' : 'info' }}">{{ $role === 'admin' ? 'Admin' : 'Pegawai' }}</span></td>
                                    <td><span class="badge badge-light-{{ $person->is_active ? 'success' : 'secondary' }}">{{ $person->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                    <td class="text-end text-nowrap">
                                        @if (in_array($role, $roles, true))
                                            <button type="button" class="btn btn-sm btn-icon btn-light-primary" title="Ubah"
                                                data-hms-drawer="#drawer-staff" data-title="Ubah Pegawai"
                                                data-action="{{ route('hms.staff.update', $person) }}" data-method="PUT"
                                                data-record="{{ json_encode($person->only('name', 'email', 'nik', 'phone', 'is_active') + ['role' => $role]) }}">
                                                <i class="ki-outline ki-pencil fs-4"></i>
                                            </button>
                                            @unless ($person->is(auth()->user()))
                                                <x-hms.delete :action="route('hms.staff.destroy', $person)" confirm="Akun pegawai ini akan dihapus." />
                                            @endunless
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-10">Belum ada pegawai.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $staff->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </x-hms.content>

    <x-hms.drawer id="drawer-staff" title="Tambah Pegawai" :action="route('hms.staff.store')">
        <div>
            <label class="form-label required">Nama lengkap</label>
            <input type="text" name="name" class="form-control form-control-solid">
        </div>
        <div>
            <label class="form-label required">Email</label>
            <input type="email" name="email" class="form-control form-control-solid">
        </div>
        <div class="row g-5">
            <div class="col-6">
                <label class="form-label">NIK</label>
                <input type="text" name="nik" class="form-control form-control-solid">
            </div>
            <div class="col-6">
                <label class="form-label">Telepon</label>
                <input type="text" name="phone" class="form-control form-control-solid">
            </div>
        </div>
        <div>
            <label class="form-label required">Jabatan</label>
            <select name="role" class="form-select form-select-solid">
                @foreach ($roles as $role)
                    <option value="{{ $role }}">{{ $role === 'admin' ? 'Admin' : 'Pegawai' }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Password</label>
            <input type="password" name="password" autocomplete="new-password" class="form-control form-control-solid">
            <div class="form-text">Minimal 8 karakter. Saat mengubah data, kosongkan jika tidak diganti.</div>
        </div>
        <label class="form-check form-switch form-check-custom form-check-solid">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
            <span class="form-check-label">Akun aktif (boleh login)</span>
        </label>
    </x-hms.drawer>
@endsection
