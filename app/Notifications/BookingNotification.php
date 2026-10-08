<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Hotel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Every guest-facing booking email: created, paid, confirmed, cancelled,
 * expired, no-show and check-in (with room number + door code). Queued, so
 * the request never waits on SMTP.
 */
class BookingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const EVENTS = ['created', 'paid', 'confirmed', 'cancelled', 'expired', 'no_show', 'checked_in', 'checked_out'];

    public function __construct(public Booking $booking, public string $event)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->fresh(['roomType', 'reservation.room']) ?? $this->booking;
        $hotel = Hotel::current();
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

        $mail = (new MailMessage)
            ->greeting('Halo ' . $booking->guest_display_name . ',')
            ->salutation('Salam hangat, ' . $hotel->name);

        $summary = sprintf(
            '%s · %s s/d %s (%d malam) · %s',
            $booking->roomType->name,
            $booking->check_in_date->translatedFormat('d M Y'),
            $booking->check_out_date->translatedFormat('d M Y'),
            $booking->nights,
            $rp($booking->total_price)
        );

        $manageUrl = $booking->user_id ? route('customer.bookings.show', $booking) : route('landing');

        return match ($this->event) {
            'created' => $mail->subject("Reservasi {$booking->booking_code} diterima")
                ->line('Terima kasih, reservasi Anda sudah kami terima.')
                ->line($summary)
                ->line($booking->payment_due_at
                    ? 'Mohon selesaikan pembayaran sebelum ' . $booking->payment_due_at->translatedFormat('d M Y H:i') . ' agar kamar tidak dilepas.'
                    : 'Kami akan segera mengonfirmasi reservasi Anda.')
                ->action('Bayar / lihat reservasi', $manageUrl),

            'paid' => $mail->subject("Pembayaran {$booking->booking_code} diterima")
                ->line('Pembayaran Anda sudah kami terima.')
                ->line($summary)
                ->action('Lihat reservasi', $manageUrl),

            'confirmed' => $mail->subject("Reservasi {$booking->booking_code} terkonfirmasi")
                ->line('Reservasi Anda sudah dikonfirmasi. Sampai jumpa!')
                ->line($summary)
                ->line('Check-in mulai pukul ' . substr((string) $hotel->check_in_time, 0, 5) . '.')
                ->action('Lihat reservasi', $manageUrl),

            'cancelled' => $mail->subject("Reservasi {$booking->booking_code} dibatalkan")
                ->line('Reservasi berikut telah dibatalkan.')
                ->line($summary)
                ->line('Alasan: ' . ($booking->cancel_reason ?: '-')),

            'expired' => $mail->subject("Reservasi {$booking->booking_code} kedaluwarsa")
                ->line('Batas waktu pembayaran sudah lewat sehingga reservasi dibatalkan otomatis.')
                ->line($summary)
                ->action('Pesan ulang', route('landing') . '#cari'),

            'no_show' => $mail->subject("Reservasi {$booking->booking_code}: tidak ada kedatangan")
                ->line('Kami tidak mencatat kedatangan Anda pada tanggal check-in, sehingga reservasi ditandai tidak datang (no-show).')
                ->line($summary),

            'checked_in' => $mail->subject('Selamat datang di ' . $hotel->name)
                ->line('Check-in berhasil. Berikut akses kamar Anda:')
                ->line('**Kamar:** ' . $booking->reservation?->room?->room_number)
                ->line('**Kode pintu:** ' . $booking->reservation?->door_passcode)
                ->line('Kode berlaku sampai check-out pada ' . $booking->check_out_date->translatedFormat('d M Y') . ' pukul ' . substr((string) $hotel->check_out_time, 0, 5) . '. Jangan bagikan kode ini.'),

            'checked_out' => $mail->subject('Terima kasih telah menginap di ' . $hotel->name)
                ->line('Check-out berhasil. Kami harap Anda menikmati masa menginap.')
                ->action($booking->user_id ? 'Beri ulasan' : 'Kunjungi kami lagi', $manageUrl),

            default => $mail->subject("Reservasi {$booking->booking_code}")->line($summary),
        };
    }
}
