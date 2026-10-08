<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Creates the first Superadmin. Credentials come from SUPERADMIN_EMAIL /
     * SUPERADMIN_PASSWORD; without a password a random one is generated and
     * printed once, so no default password ever ships in the repository.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);

        $email = env('SUPERADMIN_EMAIL', 'superadmin@example.com');
        $password = env('SUPERADMIN_PASSWORD') ?: Str::password(16);

        $user = User::where('email', $email)->first();

        if ($user) {
            $this->command?->info("Superadmin {$email} sudah ada; password tidak diubah.");
        } else {
            // 'password' is hashed by the model cast.
            $user = User::create([
                'name' => 'Super Administrator',
                'username' => 'superadmin',
                'email' => $email,
                'password' => $password,
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->command?->info('Superadmin dibuat.');
            $this->command?->info("Email   : {$email}");
            if (! env('SUPERADMIN_PASSWORD')) {
                $this->command?->warn("Password: {$password}  (simpan sekarang, tidak ditampilkan lagi)");
            }
        }

        if (! $user->hasRole('Superadmin')) {
            $user->assignRole($role);
        }
    }
}
