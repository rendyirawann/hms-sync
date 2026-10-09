<?php

namespace Database\Seeders;

use App\Events\Hms\BookingUpdated;
use App\Events\Hms\RoomBoardUpdated;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Settlement;
use App\Models\BookingCharge;
use App\Models\EmployeeShift;
use App\Models\HousekeepingTask;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Shift;
use App\Models\User;
use App\Services\Pricing;
use App\Services\SettlementService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

/**
 * Two months of believable hotel activity on top of DemoSeeder: past stays
 * with payments and reviews, guests in house right now, today's arrivals,
 * upcoming bookings in every state, housekeeping work and a staff roster.
 *
 * Re-runnable: previous demo data (booking codes "DMO-...") is replaced.
 * Never run this on production data.
 */
class DemoOperationsSeeder extends Seeder
{
    private const PAST_DAYS = 60;

    private const FUTURE_DAYS = 21;

    private const GUESTS = [
        'Andi Pratama', 'Siti Rahmawati', 'Rudi Hartono', 'Dewi Lestari', 'Agus Setiawan', 'Maya Sari',
        'Hendra Wijaya', 'Putri Anggraini', 'Fajar Nugroho', 'Lina Marlina', 'Yusuf Hidayat', 'Rina Kusuma',
    ];

    private const REVIEWS = [
        5 => ['Kamar bersih dan wangi, staf sangat ramah. Pasti kembali lagi!', 'Lokasi strategis, sarapannya enak. Recommended.', 'Pelayanan cepat, check-in tidak sampai 5 menit.'],
        4 => ['Nyaman untuk perjalanan bisnis, WiFi kencang.', 'Kamar bagus, hanya parkir agak terbatas.', 'Secara keseluruhan memuaskan, harga sebanding.'],
        3 => ['Cukup baik, AC agak berisik di malam hari.', 'Standar, sesuai harga.'],
    ];

    /** room_id => [date => true] nights already taken by a stay with a room. */
    private array $occupied = [];

    private array $counts = ['bookings' => 0, 'in_house' => 0, 'reviews' => 0, 'tasks' => 0, 'shifts' => 0, 'settlements' => 0];

    public function run(Pricing $pricing, SettlementService $settlements): void
    {
        // Seeding must not send emails, websocket events or lock commands.
        Event::fake([BookingUpdated::class, RoomBoardUpdated::class]);
        mt_srand(20261009);

        $this->clearPreviousDemo();
        $guests = $this->guests();
        $today = today();

        $hotels = Hotel::listed()->orderBy('id')->get();
        foreach ($hotels as $n => $hotel) {
            $types = RoomType::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('is_active', true)
                ->with(['rooms' => fn ($q) => $q->withoutGlobalScopes()])->get();
            if ($types->isEmpty()) {
                continue;
            }
            $promo = PromoCode::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('code', 'HEMAT10')->first();
            $admin = $hotel->owner;
            $inHouseBefore = $this->counts['in_house'];

            foreach (CarbonPeriod::create($today->copy()->subDays(self::PAST_DAYS), $today->copy()->addDays(self::FUTURE_DAYS)) as $day) {
                // Busier on Friday/Saturday, like a real hotel; smaller hotels get fewer guests.
                $max = max(1, (int) round($types->sum(fn ($t) => $t->rooms->count()) / 4));
                $arrivals = $day->isFriday() || $day->isSaturday() ? mt_rand(1, $max + 1) : mt_rand(0, $max);

                for ($i = 0; $i < $arrivals; $i++) {
                    $this->createStay($pricing, $types[mt_rand(0, $types->count() - 1)], $day->copy(), mt_rand(1, 3), $guests[array_rand($guests)], $promo, $admin);
                }
            }

            $this->ensureTodayHasActivity($pricing, $types, $guests, $admin, $inHouseBefore, $n === 0);
            $staff = User::role('Pegawai')->where('hotel_id', $hotel->id)->orderBy('email')->get();
            $this->roster($hotel, $staff);
            $this->housekeeping($hotel, $admin, $staff->last());
        }

        // Last month is settled: the first hotel already paid out, the others still open.
        $from = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $to = $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay();
        foreach ($hotels as $n => $hotel) {
            $settlement = $settlements->create($hotel, $from, $to);
            if ($settlement && $n === 0) {
                $settlement->update(['status' => 'paid', 'paid_at' => now()->subDays(3), 'reference' => 'TRF-DEMO-' . $from->format('Ym')]);
            }
            $this->counts['settlements'] += $settlement ? 1 : 0;
        }

        $this->command?->info(sprintf(
            'Data operasional demo (%d hotel): %d booking (%d tamu sedang menginap), %d ulasan, %d tugas housekeeping, %d jadwal shift, %d setoran.',
            $hotels->count(), $this->counts['bookings'], $this->counts['in_house'], $this->counts['reviews'], $this->counts['tasks'], $this->counts['shifts'], $this->counts['settlements']
        ));
    }

    private function clearPreviousDemo(): void
    {
        $ids = Booking::withoutGlobalScopes()->where('booking_code', 'like', 'DMO-%')->pluck('id');
        // Payments, reservations, charges and reviews cascade.
        Booking::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        Settlement::withoutGlobalScopes()->whereDoesntHave('bookings', fn ($q) => $q->withoutGlobalScopes())->delete();
        HousekeepingTask::withoutGlobalScopes()->where('notes', 'like', '[demo]%')->delete();
        EmployeeShift::withoutGlobalScopes()->whereIn('user_id', User::role('Pegawai')->pluck('id'))->delete();
        Room::withoutGlobalScopes()->update(['status' => 'Available', 'lock_code_ref' => null]);
    }

    /** @return array<int, User> */
    private function guests(): array
    {
        $users = [User::where('email', 'budi@tamu.test')->first()];

        foreach (self::GUESTS as $i => $name) {
            $user = User::firstOrCreate(
                ['email' => 'tamu' . ($i + 1) . '@tamu.test'],
                ['name' => $name, 'password' => 'Password123!', 'is_active' => true, 'phone' => '0812' . str_pad((string) (1000000 + $i * 7919), 7, '0', STR_PAD_LEFT)]
            );
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
            $user->syncRoles(['Customer']);
            $users[] = $user;
        }

        return array_values(array_filter($users));
    }

    private function freeRoom(RoomType $type, Carbon $in, Carbon $out): ?Room
    {
        foreach ($type->rooms->shuffle(mt_rand()) as $room) {
            $free = true;
            foreach (CarbonPeriod::create($in, $out->copy()->subDay()) as $night) {
                if (isset($this->occupied[$room->id][$night->toDateString()])) {
                    $free = false;
                    break;
                }
            }
            if ($free) {
                return $room;
            }
        }

        return null;
    }

    private function hold(Room $room, Carbon $in, Carbon $out): void
    {
        foreach (CarbonPeriod::create($in, $out->copy()->subDay()) as $night) {
            $this->occupied[$room->id][$night->toDateString()] = true;
        }
    }

    private function createStay(Pricing $pricing, RoomType $type, Carbon $in, int $nights, User $guest, ?PromoCode $promo, ?User $staff, ?string $force = null, bool $arrived = true): ?Booking
    {
        $out = $in->copy()->addDays($nights);
        $today = today();
        $room = $this->freeRoom($type, $in, $out);

        if (! $room) {
            return null; // fully booked that night — skip, as the real system would
        }

        $usePromo = $promo && mt_rand(1, 6) === 1 ? $promo : null;
        $quote = $pricing->quote($type, $in, $out, $usePromo);
        $walkIn = mt_rand(1, 8) === 1;
        $created = $in->copy()->subDays(mt_rand(0, 14))->setTime(mt_rand(7, 22), mt_rand(0, 59));
        if ($created->gt(now())) {
            $created = now()->subMinutes(mt_rand(5, 600));
        }

        $status = $force ?? $this->statusFor($in, $out, $today);

        $booking = new Booking([
            'user_id' => $walkIn ? null : $guest->id,
            'guest_name' => $walkIn ? $guest->name . ' (walk-in)' : null,
            'guest_phone' => $guest->phone ?: '081200000000',
            'room_type_id' => $type->id,
            'check_in_date' => $in->toDateString(),
            'check_out_date' => $out->toDateString(),
            'guests' => min($type->capacity, mt_rand(1, 2)),
            'room_total' => $quote['room_total'],
            'discount' => $quote['discount'],
            'service_charge' => $quote['service_charge'],
            'tax' => $quote['tax'],
            'total_price' => $quote['total'],
            'promo_code_id' => $usePromo?->id,
            'booking_code' => 'DMO-' . $in->format('ymd') . '-' . strtoupper(substr(md5((string) mt_rand()), 0, 5)),
            'status' => $status,
            'source' => $walkIn ? 'walkin' : 'online',
            'created_by' => $walkIn ? $staff?->id : null,
            'notes' => mt_rand(1, 5) === 1 ? ['Mohon kamar lantai atas', 'Datang larut malam', 'Kasur twin jika ada', 'Ulang tahun pasangan'][mt_rand(0, 3)] : null,
        ]);
        $booking->created_at = $created;
        $booking->updated_at = $created;

        if ($status === 'Pending') {
            $booking->payment_due_at = now()->addHours(mt_rand(2, 23));
        }
        if (in_array($status, ['Cancelled', 'Expired', 'NoShow'], true)) {
            $booking->cancel_reason = [
                'Cancelled' => 'Dibatalkan oleh tamu',
                'Expired' => 'Batas waktu pembayaran terlewati',
                'NoShow' => 'Tamu tidak datang pada tanggal check-in',
            ][$status];
        }
        $booking->save();
        $this->counts['bookings']++;

        if ($usePromo) {
            $usePromo->increment('used_count');
        }

        // Only stays that keep (or kept) the room block the occupancy map.
        if (in_array($status, ['Completed', 'Confirmed', 'Paid', 'Pending'], true)) {
            $this->hold($room, $in, $out);
        }

        $this->payments($booking, $status, $created, $staff);

        $inHouse = $arrived && $status === 'Confirmed' && $in->lte($today) && $out->gt($today);
        if ($status === 'Completed' || $inHouse) {
            $this->reservation($booking, $room, $in, $out, $inHouse);
        }

        if ($status === 'Completed' && $booking->user_id && mt_rand(1, 10) <= 6) {
            $rating = [5, 5, 5, 4, 4, 4, 3][mt_rand(0, 6)];
            $review = new Review([
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'room_type_id' => $type->id,
                'rating' => $rating,
                'comment' => self::REVIEWS[$rating][array_rand(self::REVIEWS[$rating])],
                'is_published' => true,
            ]);
            $review->created_at = $out->copy()->setTime(mt_rand(13, 21), 0);
            $review->save();
            $this->counts['reviews']++;
        }

        return $booking;
    }

    private function statusFor(Carbon $in, Carbon $out, Carbon $today): string
    {
        $roll = mt_rand(1, 100);

        if ($out->lte($today)) {                                   // stay is over
            return $roll <= 85 ? 'Completed' : ($roll <= 93 ? 'Cancelled' : 'NoShow');
        }
        if ($in->lte($today)) {                                    // should be in house
            return $in->isSameDay($today) && $roll > 60 ? 'Paid' : 'Confirmed'; // some still to arrive today
        }
        if ($in->diffInDays($today) <= 2) {                        // arriving soon
            return $roll <= 60 ? 'Confirmed' : ($roll <= 85 ? 'Paid' : 'Pending');
        }

        return $roll <= 40 ? 'Confirmed' : ($roll <= 60 ? 'Paid' : ($roll <= 90 ? 'Pending' : 'Cancelled'));
    }

    private function payments(Booking $booking, string $status, Carbon $created, ?User $staff): void
    {
        $paidAt = $created->copy()->addMinutes(mt_rand(10, 600));
        if ($paidAt->gt(now())) {
            $paidAt = now()->subMinute();
        }

        if (in_array($status, ['Paid', 'Confirmed', 'Completed'], true)) {
            $method = $booking->source === 'walkin' ? 'cash' : ['tripay', 'tripay', 'transfer', 'cash'][mt_rand(0, 3)];
            Payment::create([
                'booking_id' => $booking->id,
                'method' => $method,
                'channel' => $method === 'tripay' ? ['QRIS', 'BRIVA', 'BCAVA', 'OVO'][mt_rand(0, 3)] : null,
                'amount' => $booking->total_price,
                'status' => 'paid',
                'reference' => $method === 'tripay' ? 'DEV-T' . mt_rand(100000, 999999) . $booking->id : null,
                'merchant_ref' => $method === 'tripay' ? $booking->booking_code . '-P' : null,
                'paid_at' => $paidAt,
                'verified_by' => $method !== 'tripay' ? $staff?->id : null,
                'note' => $method === 'cash' ? 'Dicatat di front desk' : null,
            ]);
        } elseif ($status === 'Pending' && mt_rand(1, 3) === 1 && $booking->user_id) {
            // A guest who uploaded a transfer receipt waiting for the front desk.
            Payment::create([
                'booking_id' => $booking->id,
                'method' => 'transfer',
                'amount' => $booking->total_price,
                'status' => 'pending',
                'note' => 'Contoh bukti transfer (data demo, tanpa file)',
            ]);
        }
    }

    private function reservation(Booking $booking, Room $room, Carbon $in, Carbon $out, bool $inHouse): void
    {
        Reservation::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'actual_check_in' => $in->copy()->setTime(mt_rand(13, 20), mt_rand(0, 59)),
            'actual_check_out' => $inHouse ? null : $out->copy()->setTime(mt_rand(9, 12), mt_rand(0, 59)),
            'door_passcode' => $inHouse ? str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT) : null,
            'status' => $inHouse ? 'Active' : 'Completed',
        ]);

        if ($inHouse) {
            $room->update(['status' => 'Occupied']);
            $this->counts['in_house']++;

            if (mt_rand(1, 2) === 1) {
                BookingCharge::create([
                    'booking_id' => $booking->id,
                    'category' => 'minibar',
                    'description' => 'Air mineral & snack',
                    'quantity' => mt_rand(1, 3),
                    'unit_price' => 15000,
                    'amount' => 15000 * mt_rand(1, 3),
                ]);
            }
        } elseif (mt_rand(1, 6) === 1) {
            BookingCharge::create([
                'booking_id' => $booking->id,
                'category' => ['laundry', 'restaurant', 'late_checkout'][mt_rand(0, 2)],
                'description' => 'Layanan tambahan',
                'quantity' => 1,
                'unit_price' => 50000,
                'amount' => 50000,
            ]);
            // The extra was settled at check-out.
            Payment::create([
                'booking_id' => $booking->id, 'method' => 'cash', 'amount' => 50000, 'status' => 'paid',
                'paid_at' => $out->copy()->setTime(11, 0), 'note' => 'Pelunasan folio saat check-out',
            ]);
        }
    }

    /** Guarantee the "today" screens of each hotel have something to show. */
    private function ensureTodayHasActivity(Pricing $pricing, $types, array $guests, ?User $admin, int $inHouseBefore, bool $withBudi): void
    {
        $today = today();
        $pick = fn () => $types[mt_rand(0, $types->count() - 1)];
        $guest = fn () => $guests[array_rand($guests)];

        // Guests in house right now (started yesterday or today).
        for ($try = 0; $this->counts['in_house'] - $inHouseBefore < 2 && $try < 20; $try++) {
            $this->createStay($pricing, $pick(), $today->copy()->subDays(mt_rand(0, 1)), mt_rand(2, 3), $guest(), null, $admin, 'Confirmed');
        }

        // Arrivals expected today, not checked in yet.
        $this->createStay($pricing, $pick(), $today->copy(), mt_rand(1, 2), $guest(), null, $admin, 'Confirmed', arrived: false);
        $this->createStay($pricing, $pick(), $today->copy(), mt_rand(1, 2), $guest(), null, $admin, 'Paid', arrived: false);

        // Budi has a booking waiting for payment, to try the payment page.
        if ($withBudi && ($budi = User::where('email', 'budi@tamu.test')->first())) {
            $this->createStay($pricing, $types->first(), $today->copy()->addDays(5), 2, $budi, null, $admin, 'Pending');
        }
    }

    private function housekeeping(Hotel $hotel, ?User $admin, ?User $staff): void
    {
        $rooms = fn () => Room::withoutGlobalScopes()->where('hotel_id', $hotel->id);

        // Rooms vacated today/yesterday still need cleaning.
        $recent = Reservation::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('status', 'Completed')
            ->whereHas('booking', fn ($q) => $q->where('booking_code', 'like', 'DMO-%')->whereBetween('check_out_date', [today()->subDay(), today()]))
            ->with(['room' => fn ($q) => $q->withoutGlobalScopes()])
            ->get()
            ->unique('room_id');

        foreach ($recent as $i => $reservation) {
            $room = $reservation->room;
            if ($room->status !== 'Available') {
                continue;
            }
            $room->update(['status' => 'Cleaning']);
            HousekeepingTask::create([
                'room_id' => $room->id,
                'type' => 'checkout',
                'status' => $i % 2 ? 'in_progress' : 'pending',
                'assigned_to' => $i % 2 ? $staff?->id : null,
                'started_at' => $i % 2 ? now()->subMinutes(20) : null,
                'notes' => '[demo] Bersihkan setelah check-out',
                'created_by' => $admin?->id,
            ]);
            $this->counts['tasks']++;
        }

        // Make sure the board shows open cleaning work.
        $open = HousekeepingTask::withoutGlobalScopes()->where('hotel_id', $hotel->id)->where('notes', 'like', '[demo]%')->where('status', '!=', 'done')->count();
        foreach ($rooms()->where('status', 'Available')->whereDoesntHave('activeReservation')->orderBy('room_number')->limit(max(0, 2 - $open))->get() as $i => $room) {
            $room->update(['status' => 'Cleaning']);
            HousekeepingTask::create([
                'room_id' => $room->id,
                'type' => 'checkout',
                'status' => $i === 0 ? 'pending' : 'in_progress',
                'assigned_to' => $i === 0 ? null : $staff?->id,
                'started_at' => $i === 0 ? null : now()->subMinutes(15),
                'notes' => '[demo] Bersihkan setelah check-out pagi ini',
                'created_by' => $admin?->id,
            ]);
            $this->counts['tasks']++;
        }

        // A room out of order.
        $broken = $rooms()->where('status', 'Available')->whereDoesntHave('activeReservation')->orderByDesc('room_number')->first();
        if ($broken) {
            $broken->update(['status' => 'Maintenance']);
            HousekeepingTask::create([
                'room_id' => $broken->id, 'type' => 'maintenance', 'status' => 'pending', 'assigned_to' => $staff?->id,
                'notes' => '[demo] AC bocor, teknisi dijadwalkan', 'created_by' => $admin?->id,
            ]);
            $this->counts['tasks']++;
        }

        // History for the "Riwayat" table.
        foreach ($rooms()->inRandomOrder()->limit(4)->get() as $room) {
            $start = now()->subDays(mt_rand(1, 6))->setTime(mt_rand(10, 15), mt_rand(0, 59));
            HousekeepingTask::create([
                'room_id' => $room->id, 'type' => 'checkout', 'status' => 'done', 'assigned_to' => $staff?->id,
                'started_at' => $start, 'finished_at' => $start->copy()->addMinutes(mt_rand(20, 45)),
                'notes' => '[demo] Selesai', 'created_by' => $admin?->id,
            ]);
            $this->counts['tasks']++;
        }
    }

    private function roster(Hotel $hotel, $staff): void
    {
        $shifts = Shift::withoutGlobalScopes()->where('hotel_id', $hotel->id)->orderBy('start_time')->get();
        if ($shifts->isEmpty() || $staff->isEmpty()) {
            return;
        }

        foreach (CarbonPeriod::create(today()->subDays(14), today()->addDays(7)) as $day) {
            foreach ($staff as $i => $person) {
                if (($day->dayOfYear + $i) % 7 === 0) {
                    continue; // day off
                }
                $shift = $shifts[($day->weekOfYear + $i) % min(2, $shifts->count())]; // Pagi / Siang rotation
                $entry = new EmployeeShift(['hotel_id' => $hotel->id, 'user_id' => $person->id, 'shift_id' => $shift->id, 'date' => $day->toDateString(), 'status' => 'Pending']);

                if ($day->lt(today())) {
                    if (mt_rand(1, 15) === 1) {
                        // absent: stays Pending in the past
                    } else {
                        $start = $day->copy()->setTimeFromTimeString($shift->start_time)->addMinutes(mt_rand(-10, 25)); // sometimes late
                        $entry->fill(['status' => 'Finished', 'start_at' => $start, 'end_at' => $start->copy()->addHours(8)->addMinutes(mt_rand(-5, 20))]);
                    }
                }
                $entry->save();
                $this->counts['shifts']++;
            }
        }
    }
}
