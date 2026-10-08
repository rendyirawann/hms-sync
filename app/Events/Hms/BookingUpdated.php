<?php

namespace App\Events\Hms;

use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushed to the front desk and to the guest who owns the booking whenever its
 * state changes. Queued (ShouldBroadcast) so a Reverb outage never breaks
 * the request; dispatched after commit so listeners never see rolled-back data.
 * The payload deliberately carries no door code.
 */
class BookingUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const MESSAGES = [
        'created' => 'Reservasi baru',
        'proof_uploaded' => 'Bukti transfer masuk',
        'paid' => 'Pembayaran diterima',
        'confirmed' => 'Reservasi dikonfirmasi',
        'cancelled' => 'Reservasi dibatalkan',
        'expired' => 'Reservasi kedaluwarsa',
        'no_show' => 'Tamu tidak datang',
        'checked_in' => 'Tamu check-in',
        'checked_out' => 'Tamu check-out',
        'extended' => 'Masa inap diperpanjang',
        'moved' => 'Tamu pindah kamar',
    ];

    public function __construct(public Booking $booking, public string $action)
    {
    }

    public function broadcastAs(): string
    {
        return 'booking.updated';
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('hms.frontdesk')];

        if ($this->booking->user_id) {
            $channels[] = new PrivateChannel('App.Models.User.' . $this->booking->user_id);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        $this->booking->loadMissing(['user:id,name', 'roomType:id,name']);

        return [
            'id' => $this->booking->id,
            'code' => $this->booking->booking_code,
            'action' => $this->action,
            'message' => (self::MESSAGES[$this->action] ?? 'Reservasi diperbarui') . ' · ' . $this->booking->booking_code,
            'status' => $this->booking->status,
            'status_label' => $this->booking->status_label,
            'guest' => $this->booking->guest_display_name,
            'room_type' => $this->booking->roomType?->name,
        ];
    }
}
