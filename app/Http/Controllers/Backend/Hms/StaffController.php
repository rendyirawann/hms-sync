<?php

namespace App\Http\Controllers\Backend\Hms;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Hotel employees: accounts with the "Pegawai" or "admin" role. */
class StaffController extends Controller
{
    use RespondsWithAlert;

    public const ROLES = ['Pegawai', 'admin'];

    public function index(Request $request)
    {
        $staff = User::role(self::ROLES)
            ->with('roles')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term)->orWhere('nik', 'ilike', $term));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('backend.hms.staff.index', [
            'staff' => $staff,
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'nik' => $data['nik'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'is_active' => $request->boolean('is_active'),
        ]);
        // Created by the hotel, so the address is trusted - no activation mail.
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles([$data['role']]);

        return $this->done($request, 'Pegawai berhasil ditambahkan.');
    }

    public function update(Request $request, User $staff)
    {
        $this->ensureStaff($staff);
        $data = $this->validated($request, $staff);

        $staff->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'nik' => $data['nik'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        if (! empty($data['password'])) {
            $staff->password = $data['password'];
        }
        $staff->save();
        $staff->syncRoles([$data['role']]);

        return $this->done($request, 'Data pegawai berhasil diperbarui.');
    }

    public function destroy(Request $request, User $staff)
    {
        $this->ensureStaff($staff);

        if ($staff->is($request->user())) {
            return $this->fail($request, 'Anda tidak bisa menghapus akun sendiri.');
        }

        $staff->delete();

        return $this->done($request, 'Pegawai berhasil dihapus.');
    }

    /** Only a Superadmin may create or promote another admin. */
    private function assignableRoles(): array
    {
        return auth()->user()->hasRole('Superadmin') ? self::ROLES : ['Pegawai'];
    }

    private function ensureStaff(User $user): void
    {
        abort_unless($user->hasAnyRole(self::ROLES), 404);
        abort_if($user->hasRole('admin') && ! auth()->user()->hasRole('Superadmin'), 403);
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'nik' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in($this->assignableRoles())],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
        ], [], ['name' => 'nama', 'phone' => 'telepon', 'role' => 'jabatan']);
    }
}
