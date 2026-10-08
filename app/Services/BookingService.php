<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\HousekeepingTask;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomMove;
use App\Models\RoomType;
use App\Events\Hms\BookingUpdated;
use App\Notifications\BookingNotification;
use App\Services\DoorLock\DoorLock;
use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every state change of a booking goes through here so the rules (inventory
 * lock, pricing, payment deadline, notifications, door codes, housekeeping)
 * stay identical for the website, the front desk and the scheduler.
 */
class BookingService
{
    /** Set when the smart lock could not be updated; shown to the front desk. */
    public ?string $lockWarning = null;

    public function __construct(
        private RoomAvailability $availability,
        private Pricing $pricing,
        private DoorLock $doorLock,
    ) {
    }

    /**
     * @param  array{check_in:string, check_out:string, guests:int, guest_phone?:?string, notes?:?string,
     *               guest_name?:?string, guest_email?:?string, promo_code?:?string}  $data
     */
    public function create(RoomType $type, array $data, ?string $userId, string $source = 'online', ?string $createdBy = null): Booking
    {
        $checkIn = Carbon::parse($data['check_in'])->startOfDay();
        $checkOut = Carbon::parse($data['check_out'])->startOfDay();
        $promo = $this->resolvePromo($data['promo_code'] ?? null, $checkIn, $checkOut);

        $booking = DB::transaction(function () use ($type, $data, $userId, $source, $createdBy, $checkIn, $checkOut, $promo) {
            // Serialise bookings of the same room type so two guests cannot
            // both take the last room.
            RoomType::whereKey($type->id)->lockForUpdate()->first();

            if ($this->availability->available($type, $checkIn, $checkOut) < 1) {
                throw ValidationException::withMessages(['check_in' => 'Maaf, kamar tipe ini sudah penuh untuk tanggal tersebut.']);
            }

            if ($promo) {
                // Re-check the quota under lock.
                $promo = PromoCode::whereKey($promo->id)->lockForUpdate()->first();
                if ($reason = $promo->rejectionReason($checkIn->diffInDays($checkOut), $checkIn)) {
                    throw ValidationException::withMessages(['promo_code' => $reason]);
                }
                $promo->increment('used_count');
            }

            $quote = $this->pricing->quote($type, $checkIn, $checkOut, $promo);
            $deadline = Hotel::current()->payment_deadline_hours;

            return Booking::create([
                'user_id' => $userId,
                'room_type_id' => $type->id,
                'check_in_date' => $checkIn->toDateString(),
                'check_out_date' => $checkOut->toDateString(),
                'room_total' => $quote['room_total'],
                'discount' => $quote['discount'],
                'service_charge' => $quote['service_charge'],
                'tax' => $quote['tax'],
                'total_price' => $quote['total'],
                'promo_code_id' => $promo?->id,
                'booking_code' => $this->newCode(),
                'status' => 'Pending',
                'guests' => $data['guests'],
                'guest_phone' => $data['guest_phone'] ?? null,
                'guest_name' => $data['guest_name'] ?? null,
                'guest_email' => $data['guest_email'] ?? null,
                'notes' => $data['notes'] ?? null,
                'source' => $source,
                'created_by' => $createdBy,
                // Walk-ins pay at the desk; online bookings must pay in time.
                'payment_due_at' => $source === 'online' && $deadline > 0 ? now()->addHours($deadline) : null,
            ]);
        });

        $this->notify($booking, 'created');

        return $booking;
    }

    public function resolvePromo(?string $code, Carbon $checkIn, Carbon $checkOut): ?PromoCode
    {
        if (blank($code)) {
            return null;
        }

        $promo = PromoCode::where('code', strtoupper(trim($code)))->first();

        if (! $promo) {
            throw ValidationException::withMessages(['promo_code' => 'Kode promo tidak ditemukan.']);
        }

        if ($reason = $promo->rejectionReason($checkIn->diffInDays($checkOut), $checkIn)) {
            throw ValidationException::withMessages(['promo_code' => $reason]);
        }

        return $promo;
    }

    /** Record a settled payment; the first full settlement moves Pending to Paid. */
    public function markPaid(Booking $booking, Payment $payment): void
    {
        DB::transaction(function () use ($booking, $payment) {
            $payment->update(['status' => 'paid', 'paid_at' => $payment->paid_at ?? now()]);

            if ($booking->status === 'Pending') {
                $booking->update(['status' => 'Paid', 'payment_due_at' => null]);
            }
        });

        $this->notify($booking, 'paid');
    }

    public function confirm(Booking $booking): void
    {
        $booking->update(['status' => 'Confirmed', 'payment_due_at' => null]);
        $this->notify($booking, 'confirmed');
    }

    /** Cancel / expire / no-show: all release the inventory and void open payments. */
    public function release(Booking $booking, string $status, string $reason, string $event): void
    {
        DB::transaction(function () use ($booking, $status, $reason) {
            $booking->update(['status' => $status, 'cancel_reason' => $reason, 'payment_due_at' => null]);
            $booking->payments()->where('status', 'pending')->update(['status' => 'expired']);

            if ($booking->promo_code_id && $status !== 'NoShow') {
                PromoCode::whereKey($booking->promo_code_id)->where('used_count', '>', 0)->decrement('used_count');
            }
        });

        $this->notify($booking, $event);
    }

    public function checkIn(Booking $booking, int $roomId): Reservation
    {
        $reservation = DB::transaction(function () use ($booking, $roomId) {
            // Lock the room so two clerks cannot hand out the same key.
            $room = Room::whereKey($roomId)->where('room_type_id', $booking->room_type_id)->lockForUpdate()->first();

            if (! $room || $room->status !== 'Available' || $room->activeReservation()->exists()) {
                throw ValidationException::withMessages(['room_id' => 'Kamar tersebut tidak tersedia. Pilih kamar lain.']);
            }

            $reservation = Reservation::create([
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'actual_check_in' => now(),
                'door_passcode' => $this->newPasscode(),
                'status' => 'Active',
            ]);

            $room->update(['status' => 'Occupied']);
            $booking->update(['status' => 'Confirmed', 'payment_due_at' => null]);

            return $reservation;
        });

        $this->lock(fn () => $this->doorLock->grant($reservation->room, $reservation->door_passcode, now(), $booking->check_out_date->copy()->setTimeFromTimeString((string) Hotel::current()->check_out_time)));
        $this->notify($booking, 'checked_in');

        return $reservation;
    }

    public function checkOut(Booking $booking, ?string $by = null): void
    {
        $reservation = $booking->reservation;

        DB::transaction(function () use ($booking, $reservation, $by) {
            $reservation->update(['status' => 'Completed', 'actual_check_out' => now(), 'door_passcode' => null]);
            $reservation->room->update(['status' => 'Cleaning']);
            $booking->update(['status' => 'Completed']);

            HousekeepingTask::create([
                'room_id' => $reservation->room_id,
                'type' => 'checkout',
                'status' => 'pending',
                'notes' => 'Otomatis setelah check-out ' . $booking->booking_code,
                'created_by' => $by,
            ]);
        });

        $this->lock(fn () => $this->doorLock->revoke($reservation->room));
        $this->notify($booking, 'checked_out');
    }

    public function regeneratePasscode(Reservation $reservation): string
    {
        $code = $this->newPasscode();
        $reservation->update(['door_passcode' => $code]);
        $this->lock(fn () => $this->doorLock->grant($reservation->room, $code, now(), $reservation->booking->check_out_date->copy()->setTimeFromTimeString((string) Hotel::current()->check_out_time)));

        return $code;
    }

    /** Extend the stay by N nights; priced like a normal stay and billed to the folio. */
    public function extend(Booking $booking, string $newCheckOut, ?string $by = null): float
    {
        $newOut = Carbon::parse($newCheckOut)->startOfDay();
        $oldOut = $booking->check_out_date->copy();

        if ($newOut->lte($oldOut)) {
            throw ValidationException::withMessages(['check_out' => 'Tanggal check-out baru harus setelah ' . $oldOut->translatedFormat('d M Y') . '.']);
        }

        $amount = DB::transaction(function () use ($booking, $newOut, $oldOut, $by) {
            $type = RoomType::whereKey($booking->room_type_id)->lockForUpdate()->first();

            // The guest keeps their room, so only the extra nights need a free unit of this type.
            if ($this->availability->available($type, $oldOut, $newOut) < 1) {
                throw ValidationException::withMessages(['check_out' => 'Tipe kamar ini penuh pada tanggal perpanjangan.']);
            }

            $quote = $this->pricing->quote($type, $oldOut, $newOut);
            $booking->update(['check_out_date' => $newOut->toDateString()]);
            $booking->charges()->create([
                'category' => 'extend',
                'description' => sprintf('Perpanjangan %d malam (%s - %s)', $oldOut->diffInDays($newOut), $oldOut->format('d/m'), $newOut->format('d/m')),
                'quantity' => 1,
                'unit_price' => $quote['total'],
                'amount' => $quote['total'],
                'created_by' => $by,
            ]);

            return $quote['total'];
        });

        $this->announce($booking, 'extended');

        return $amount;
    }

    public function moveRoom(Booking $booking, int $toRoomId, ?string $reason, ?string $by = null): Room
    {
        $reservation = $booking->reservation;
        $fromRoom = $reservation->room;

        $to = DB::transaction(function () use ($reservation, $toRoomId, $reason, $by) {
            $to = Room::whereKey($toRoomId)->lockForUpdate()->first();

            if (! $to || $to->status !== 'Available' || $to->activeReservation()->exists() || $to->id === $reservation->room_id) {
                throw ValidationException::withMessages(['room_id' => 'Kamar tujuan tidak tersedia.']);
            }

            $from = $reservation->room;
            RoomMove::create([
                'reservation_id' => $reservation->id,
                'from_room_id' => $from->id,
                'to_room_id' => $to->id,
                'reason' => $reason,
                'moved_by' => $by,
            ]);

            $from->update(['status' => 'Cleaning']);
            HousekeepingTask::create([
                'room_id' => $from->id, 'type' => 'checkout', 'status' => 'pending',
                'notes' => 'Tamu pindah ke kamar ' . $to->room_number, 'created_by' => $by,
            ]);

            $to->update(['status' => 'Occupied']);
            $reservation->update(['room_id' => $to->id]);

            return $to;
        });

        $this->lock(fn () => $this->doorLock->revoke($fromRoom));
        $this->announce($booking, 'moved');
        $this->lock(fn () => $this->doorLock->grant($to, $reservation->door_passcode, now(), $booking->check_out_date->copy()->setTimeFromTimeString((string) Hotel::current()->check_out_time)));

        return $to;
    }

    /** Run a smart-lock call; the stay goes ahead even when the lock is unreachable. */
    private function lock(callable $call): void
    {
        try {
            $call();
        } catch (Throwable $e) {
            report($e);
            $this->lockWarning = 'Kunci pintu belum tersinkron: ' . $e->getMessage() . '. Atur kode secara manual atau coba "Passcode Baru".';
        }
    }

    /** Realtime push to the front desk and the guest (Reverb). */
    public function announce(Booking $booking, string $action): void
    {
        BookingUpdated::dispatch($booking, $action);
    }

    public function notify(Booking $booking, string $event): void
    {
        $this->announce($booking, $event);
        $booking->loadMissing('user');

        if ($booking->user) {
            $booking->user->notify(new BookingNotification($booking, $event));
        } elseif ($booking->guest_email) {
            Notification::route('mail', $booking->guest_email)->notify(new BookingNotification($booking, $event));
        }
    }

    private function newCode(): string
    {
        do {
            $code = 'HMS-' . now()->format('ymd') . '-' . Str::upper(Str::random(5));
        } while (Booking::where('booking_code', $code)->exists());

        return $code;
    }

    /**
     * 6-digit door code, unique among rooms currently occupied. Codes are
     * encrypted at rest, so uniqueness is checked in PHP over the (small)
     * set of active stays.
     */
    private function newPasscode(): string
    {
        $inUse = Reservation::where('status', 'Active')->get(['id', 'door_passcode'])->pluck('door_passcode')->filter()->all();

        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (in_array($code, $inUse, true));

        return $code;
    }
}
