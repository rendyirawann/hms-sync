<?php

namespace Tests\Feature\Hms;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

abstract class HmsTestCase extends TestCase
{
    use RefreshDatabase;

    protected Hotel $hotel;

    protected RoomType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Notification::fake();

        // Rate limits are not what these tests exercise.
        $this->withoutMiddleware([\Illuminate\Routing\Middleware\ThrottleRequests::class]);

        $this->hotel = Hotel::create([
            'name' => 'Hotel Uji',
            'address' => 'Jl. Uji 1',
            'tax_percent' => 10,
            'service_percent' => 5,
            'payment_deadline_hours' => 24,
            'bank_accounts' => 'BCA 123 a.n. Hotel Uji',
        ]);

        $this->type = RoomType::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Deluxe',
            'capacity' => 2,
            'base_price' => 500000,
            'is_active' => true,
        ]);

        Room::create(['room_type_id' => $this->type->id, 'room_number' => '101', 'status' => 'Available']);
    }

    /** Hotel staff (admin / Pegawai) belong to the test hotel unless told otherwise. */
    protected function user(string $role, array $attributes = []): User
    {
        if (in_array($role, ['admin', 'Pegawai'], true) && ! array_key_exists('hotel_id', $attributes)) {
            $attributes['hotel_id'] = $this->hotel->id;
        }

        $user = User::factory()->create($attributes + ['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    /** A Superadmin who has opened a hotel's panel with "Kelola". */
    protected function superadminIn(?Hotel $hotel = null): User
    {
        $this->withSession([\App\Http\Middleware\ResolveHotelContext::SESSION_KEY => ($hotel ?? $this->hotel)->id]);

        return $this->user('Superadmin');
    }

    protected function addRoom(string $number, string $status = 'Available'): Room
    {
        return Room::create(['room_type_id' => $this->type->id, 'room_number' => $number, 'status' => $status]);
    }

    /** JSON headers used by the admin's AJAX forms. */
    protected function ajax(): static
    {
        return $this->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);
    }
}
