<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The starter template seeded generic "StarterTemp" branding. Replace it with
 * hotel wording, but only where the value is still the template default so
 * anything an operator already customised in Settings is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        $name = config('app.name');
        $name = ($name && $name !== 'StarterTemp' && $name !== 'Laravel') ? $name : 'HMS Sync';

        $replacements = [
            'site_name' => ['StarterTemp', $name],
            'site_short_name' => ['StarterTemp', mb_substr($name, 0, 12)],
            'site_tagline' => ['Modern Admin Dashboard', 'Reservasi & Manajemen Hotel'],
            'site_keywords' => [null, 'hotel, reservasi kamar, booking hotel, ' . strtolower($name)],
        ];

        foreach ($replacements as $key => [$old, $new]) {
            $query = DB::table('settings')->where('key', $key);
            $current = $query->value('value');

            if ($current === null || $current === $old || ($old === null && str_contains((string) $current, 'admin panel'))) {
                DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $new, 'updated_at' => now()]);
            }
        }

        $description = DB::table('settings')->where('key', 'site_description')->value('value');
        if ($description === null || str_contains($description, 'panel administrasi modern')) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'site_description'],
                ['value' => "Pesan kamar langsung di {$name}: cek ketersediaan, bayar online, dan dapatkan kode pintu kamar saat check-in.", 'updated_at' => now()]
            );
        }

        Cache::forget('settings.all');
    }

    public function down(): void
    {
        // Branding text is content, not schema; nothing to roll back.
    }
};
