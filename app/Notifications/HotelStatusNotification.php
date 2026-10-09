<?php

namespace App\Notifications;

use App\Models\Hotel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a hotel owner the outcome of their marketplace registration. */
class HotelStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Hotel $hotel, public string $event)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $platform = config('app.name');
        $mail = (new MailMessage)->greeting('Halo ' . $notifiable->name . ',');

        return match ($this->event) {
            'registered' => $mail->subject('Pendaftaran ' . $this->hotel->name . ' kami terima')
                ->line('Terima kasih telah mendaftarkan ' . $this->hotel->name . ' di ' . $platform . '.')
                ->line('Tim kami akan memeriksa data hotel Anda. Sambil menunggu, Anda sudah bisa login dan menyiapkan tipe kamar, kamar, dan foto.')
                ->action('Buka panel hotel', route('dashboard')),
            'approved' => $mail->subject($this->hotel->name . ' sudah tayang di ' . $platform)
                ->line('Selamat! Hotel Anda telah disetujui dan kini tampil di website sehingga tamu bisa memesan.')
                ->action('Lihat halaman hotel', route('hotels.show', $this->hotel)),
            default => $mail->subject('Pendaftaran ' . $this->hotel->name . ' belum disetujui')
                ->line('Mohon maaf, pendaftaran hotel Anda belum dapat kami setujui.')
                ->line('Alasan: ' . ($this->hotel->rejection_reason ?: '-'))
                ->line('Silakan perbaiki data lalu hubungi tim kami.'),
        };
    }
}
