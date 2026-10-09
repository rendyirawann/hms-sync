<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\PromoCode;
use App\Models\RateRule;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample hotel for demos / UAT:  php artisan db:seed --class=DemoSeeder
 * Never run this on production data.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $hotel = Hotel::current();
        $hotel->update([
            'name' => 'Hotel Nusantara Demo',
            'address' => 'Jl. Merdeka No. 10, Bandung, Jawa Barat',
            'description' => 'Hotel butik di pusat kota Bandung, 5 menit dari Alun-alun. Kamar nyaman, sarapan lokal, dan pelayanan ramah.',
            'phone' => '022-1234567',
            'email' => 'reservasi@nusantara.test',
            'rating' => 4.6,
            'tax_percent' => 11,
            'service_percent' => 10,
            'payment_deadline_hours' => 24,
            'bank_accounts' => "BCA 1234567890 a.n. PT Nusantara Demo\nMandiri 0987654321 a.n. PT Nusantara Demo",
        ]);

        $types = [
            ['Standard Twin', 2, 450000, ['WiFi', 'AC', 'TV', 'Air panas'], 'Kamar 20 m² dengan dua kasur single, cocok untuk perjalanan bisnis.', ['101', '102', '103', '104']],
            ['Deluxe Double', 2, 650000, ['WiFi', 'AC', 'TV', 'Sarapan', 'Minibar'], 'Kamar 28 m² dengan kasur queen dan pemandangan kota.', ['201', '202', '203']],
            ['Family Suite', 4, 1200000, ['WiFi', 'AC', 'Smart TV', 'Sarapan', 'Bathtub', 'Ruang tamu'], 'Suite 45 m² dengan dua kamar tidur, ideal untuk keluarga.', ['301', '302']],
        ];

        foreach ($types as [$name, $capacity, $price, $amenities, $description, $rooms]) {
            $type = RoomType::firstOrCreate(['name' => $name], [
                'hotel_id' => $hotel->id,
                'capacity' => $capacity,
                'base_price' => $price,
                'amenities' => $amenities,
                'description' => $description,
                'is_active' => true,
            ]);

            foreach ($rooms as $number) {
                Room::firstOrCreate(['room_number' => $number], [
                    'room_type_id' => $type->id,
                    'floor' => substr($number, 0, 1),
                    'status' => 'Available',
                ]);
            }
        }

        RateRule::firstOrCreate(['name' => 'Weekend'], [
            'days_of_week' => [5, 6], 'adjust_type' => 'percent', 'value' => 20, 'priority' => 1, 'is_active' => true,
        ]);
        PromoCode::firstOrCreate(['code' => 'HEMAT10'], [
            'type' => 'percent', 'value' => 10, 'max_discount' => 200000, 'min_nights' => 1, 'is_active' => true,
        ]);

        foreach ([['Pagi', '07:00', '15:00'], ['Siang', '15:00', '23:00'], ['Malam', '23:00', '07:00']] as [$name, $start, $end]) {
            Shift::firstOrCreate(['name' => $name], ['start_time' => $start, 'end_time' => $end]);
        }

        $accounts = [
            ['Admin Hotel', 'admin@nusantara.test', 'admin'],
            ['Rina Resepsionis', 'rina@nusantara.test', 'Pegawai'],
            ['Budi Tamu', 'budi@tamu.test', 'Customer'],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'Password123!',
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
            $user->syncRoles([$role]);
        }

        // Bookings, stays, payments, reviews, housekeeping and roster.
        $this->call(DemoOperationsSeeder::class);

        $this->command?->info('Demo data siap. Akun demo (password: Password123!): admin@nusantara.test, rina@nusantara.test, budi@tamu.test');
    }
}
