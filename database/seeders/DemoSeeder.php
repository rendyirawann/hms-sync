<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\PromoCode;
use App\Models\RateRule;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample marketplace for demos / UAT:  php artisan db:seed --class=DemoSeeder
 * Three live hotels in different cities, one waiting for approval, a
 * platform-wide promo, then two months of activity (DemoOperationsSeeder).
 * Never run this on production data.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Password123!';

    /** slug => [hotel attributes, admin, staff, room types] */
    private function hotels(): array
    {
        return [
            'hotel-nusantara-bandung' => [
                'hotel' => [
                    'name' => 'Hotel Nusantara Bandung', 'city' => 'Bandung', 'province' => 'Jawa Barat',
                    'address' => 'Jl. Merdeka No. 10, Bandung', 'phone' => '022-1234567', 'email' => 'reservasi@nusantara.test',
                    'description' => 'Hotel butik di pusat kota Bandung, 5 menit dari Alun-alun. Kamar nyaman, sarapan lokal, dan pelayanan ramah.',
                    'rating' => 4.6, 'commission_percent' => 10,
                ],
                'admin' => ['Admin Nusantara', 'admin@nusantara.test'],
                'staff' => [['Rina Resepsionis', 'rina@nusantara.test'], ['Dedi Housekeeping', 'dedi@nusantara.test']],
                'types' => [
                    ['Standard Twin', 2, 450000, ['WiFi', 'AC', 'TV', 'Air panas'], 'Kamar 20 m² dengan dua kasur single, cocok untuk perjalanan bisnis.', ['101', '102', '103', '104']],
                    ['Deluxe Double', 2, 650000, ['WiFi', 'AC', 'TV', 'Sarapan', 'Minibar'], 'Kamar 28 m² dengan kasur queen dan pemandangan kota.', ['201', '202', '203']],
                    ['Family Suite', 4, 1200000, ['WiFi', 'AC', 'Smart TV', 'Sarapan', 'Bathtub', 'Ruang tamu'], 'Suite 45 m² dengan dua kamar tidur, ideal untuk keluarga.', ['301', '302']],
                ],
            ],
            'griya-malioboro-yogyakarta' => [
                'hotel' => [
                    'name' => 'Griya Malioboro', 'city' => 'Yogyakarta', 'province' => 'DI Yogyakarta',
                    'address' => 'Jl. Sosrowijayan No. 21, Yogyakarta', 'phone' => '0274-556677', 'email' => 'halo@griyamalioboro.test',
                    'description' => 'Penginapan bernuansa Jawa, jalan kaki 3 menit ke Malioboro. Sarapan gudeg setiap pagi.',
                    'rating' => 4.4, 'commission_percent' => 12,
                ],
                'admin' => ['Admin Griya', 'admin@griya.test'],
                'staff' => [['Sari Front Desk', 'sari@griya.test']],
                'types' => [
                    ['Superior Queen', 2, 380000, ['WiFi', 'AC', 'Sarapan'], 'Kamar 18 m² dengan kasur queen dan sentuhan batik.', ['A1', 'A2', 'A3', 'A4']],
                    ['Joglo Suite', 3, 850000, ['WiFi', 'AC', 'Sarapan', 'Teras pribadi'], 'Suite bergaya joglo dengan teras menghadap taman.', ['J1', 'J2']],
                ],
            ],
            'ombak-resort-bali' => [
                'hotel' => [
                    'name' => 'Ombak Resort Canggu', 'city' => 'Bali', 'province' => 'Bali',
                    'address' => 'Jl. Pantai Batu Bolong No. 8, Canggu', 'phone' => '0361-998877', 'email' => 'stay@ombak.test',
                    'description' => 'Resort tepi pantai dengan kolam renang infinity, 2 menit ke spot selancar Batu Bolong.',
                    'rating' => 4.8, 'commission_percent' => 15,
                ],
                'admin' => ['Admin Ombak', 'admin@ombak.test'],
                'staff' => [['Made Reception', 'made@ombak.test']],
                'types' => [
                    ['Garden Villa', 2, 1350000, ['WiFi', 'AC', 'Sarapan', 'Kolam bersama'], 'Villa 40 m² dengan taman tropis pribadi.', ['V01', 'V02', 'V03']],
                    ['Ocean Pool Villa', 4, 2900000, ['WiFi', 'AC', 'Sarapan', 'Kolam pribadi', 'Butler'], 'Villa dua kamar dengan kolam pribadi menghadap laut.', ['V10', 'V11']],
                ],
            ],
        ];
    }

    public function run(): void
    {
        // Platform settings: where guests transfer, default commission.
        Setting::set('platform_bank_accounts', "BCA 8800112233 a.n. PT HMS Sync Indonesia\nMandiri 1330099887766 a.n. PT HMS Sync Indonesia");
        Setting::set('platform_default_commission', '10');
        Setting::set('allow_registration', '1');

        // Databases seeded before the marketplace had one "Hotel Nusantara Demo": reuse it.
        if (! Hotel::where('slug', 'hotel-nusantara-bandung')->exists()) {
            Hotel::where('slug', 'hotel-nusantara-demo')->update(['slug' => 'hotel-nusantara-bandung']);
        }

        foreach ($this->hotels() as $slug => $def) {
            $this->seedHotel($slug, $def);
        }

        // One partner who signed up and waits for approval.
        $pending = Hotel::updateOrCreate(['slug' => 'wisma-apel-malang'], [
            'name' => 'Wisma Apel Malang', 'city' => 'Malang', 'province' => 'Jawa Timur',
            'address' => 'Jl. Ijen No. 5, Malang', 'phone' => '0341-445566',
            'approval_status' => 'pending', 'status' => false, 'commission_percent' => 10,
        ]);
        $owner = $this->account('Pemilik Wisma Apel', 'pemilik@wismaapel.test', 'admin', $pending->id);
        $pending->update(['owner_id' => $owner->id]);

        // Platform-wide promo (hotel_id null) on top of each hotel's own promo.
        PromoCode::withoutGlobalScopes()->updateOrCreate(['hotel_id' => null, 'code' => 'HMSHEMAT'], [
            'type' => 'percent', 'value' => 5, 'max_discount' => 100000, 'min_nights' => 2, 'is_active' => true,
        ]);

        foreach ([['Budi Tamu', 'budi@tamu.test']] as [$name, $email]) {
            $this->account($name, $email, 'Customer');
        }

        // Bookings, stays, payments, reviews, housekeeping, roster, settlements.
        $this->call(DemoOperationsSeeder::class);

        $this->command?->info('Demo marketplace siap. Password semua akun demo: ' . self::PASSWORD);
    }

    private function seedHotel(string $slug, array $def): void
    {
        $hotel = Hotel::updateOrCreate(['slug' => $slug], $def['hotel'] + [
            'approval_status' => 'approved',
            'approved_at' => now(),
            'status' => true,
            'tax_percent' => 11,
            'service_percent' => 10,
            'payment_deadline_hours' => 24,
            'payout_bank' => 'BCA',
            'payout_account_number' => (string) (5000000000 + crc32($slug) % 999999999),
            'payout_account_name' => $def['hotel']['name'],
        ]);

        $admin = $this->account($def['admin'][0], $def['admin'][1], 'admin', $hotel->id);
        $hotel->update(['owner_id' => $admin->id]);
        foreach ($def['staff'] as [$name, $email]) {
            $this->account($name, $email, 'Pegawai', $hotel->id);
        }

        foreach ($def['types'] as [$name, $capacity, $price, $amenities, $description, $rooms]) {
            $type = RoomType::withoutGlobalScopes()->updateOrCreate(['hotel_id' => $hotel->id, 'name' => $name], [
                'capacity' => $capacity, 'base_price' => $price, 'amenities' => $amenities,
                'description' => $description, 'is_active' => true,
            ]);

            foreach ($rooms as $number) {
                Room::withoutGlobalScopes()->firstOrCreate(['hotel_id' => $hotel->id, 'room_number' => $number], [
                    'room_type_id' => $type->id,
                    'floor' => preg_replace('/\D/', '', $number) ? substr(preg_replace('/\D/', '', $number), 0, 1) : null,
                    'status' => 'Available',
                ]);
            }
        }

        RateRule::withoutGlobalScopes()->updateOrCreate(['hotel_id' => $hotel->id, 'name' => 'Weekend'], [
            'days_of_week' => [5, 6], 'adjust_type' => 'percent', 'value' => 20, 'priority' => 1, 'is_active' => true,
        ]);
        PromoCode::withoutGlobalScopes()->updateOrCreate(['hotel_id' => $hotel->id, 'code' => 'HEMAT10'], [
            'type' => 'percent', 'value' => 10, 'max_discount' => 200000, 'min_nights' => 1, 'is_active' => true,
        ]);

        foreach ([['Pagi', '07:00', '15:00'], ['Siang', '15:00', '23:00'], ['Malam', '23:00', '07:00']] as [$name, $start, $end]) {
            Shift::withoutGlobalScopes()->firstOrCreate(['hotel_id' => $hotel->id, 'name' => $name], ['start_time' => $start, 'end_time' => $end]);
        }
    }

    private function account(string $name, string $email, string $role, ?int $hotelId = null): User
    {
        $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => self::PASSWORD, 'is_active' => true]);
        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
            'hotel_id' => $hotelId,
        ])->save();
        $user->syncRoles([$role]);

        return $user;
    }
}
