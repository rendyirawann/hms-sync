# HMS Sync

Sistem manajemen hotel (Hotel Management System) untuk **satu properti**: website pemesanan untuk tamu, panel front desk untuk resepsionis, serta pengelolaan kamar, harga, pegawai, dan laporan untuk manajemen.

Dibangun dengan **Laravel 12**, **PostgreSQL**, panel admin **Metronic (Bootstrap 5)**, dan halaman publik **Tailwind CSS v4**.

---

## Daftar isi

- [Fitur](#fitur)
- [Alur utama](#alur-utama)
- [Peran & hak akses](#peran--hak-akses)
- [Teknologi](#teknologi)
- [Menjalankan di lokal](#menjalankan-di-lokal)
- [Konfigurasi penting](#konfigurasi-penting)
- [Tugas terjadwal](#tugas-terjadwal)
- [Testing](#testing)
- [Struktur kode](#struktur-kode)
- [Deploy ke server](#deploy-ke-server)

---

## Fitur

### Untuk tamu (website publik)
- Landing page berisi data hotel, tipe kamar dengan harga "mulai dari", galeri fasilitas, dan ulasan tamu.
- Cek ketersediaan kamar per tanggal dan jumlah tamu, dengan harga final sudah termasuk pajak dan service charge.
- Daftar akun dengan **aktivasi email**. Akun yang belum aktif tidak bisa login, dan link aktivasi bisa diminta ulang.
- Pemesanan dengan **kode promo**, rincian harga per malam, dan batas waktu pembayaran.
- Pembayaran online lewat **Tripay** (VA, QRIS, e-wallet, gerai retail) atau **transfer bank manual** dengan unggah bukti.
- Halaman "Reservasi Saya": status, kode pintu kamar saat menginap, unduh **invoice PDF**, batalkan, dan beri **ulasan** setelah check-out.
- Profil tamu: ubah nama, nomor HP, dan password.
- Email otomatis di setiap tahap: reservasi dibuat, dibayar, dikonfirmasi, dibatalkan, kedaluwarsa, check-in (berisi nomor kamar dan kode pintu), dan check-out.

### Untuk front desk
- Daftar reservasi dengan filter dan penanda bukti transfer yang perlu dicek.
- **Booking walk-in** untuk tamu tanpa akun.
- Verifikasi bukti transfer, catat pembayaran tunai/EDC, konfirmasi, dan pembatalan.
- **Check-in**: pilih kamar, lalu kode pintu 6 digit dibuat otomatis (tersimpan terenkripsi).
- Saat tamu menginap: **folio** (minibar, laundry, restoran, late check-out), **perpanjang menginap**, **pindah kamar**, dan buat ulang kode pintu.
- **Check-out** hanya bisa dilakukan setelah tagihan lunas. Kamar otomatis masuk antrean housekeeping.
- **Kalender okupansi**: sisa kamar per tipe dan grid kamar × tanggal untuk 14 hari.

### Untuk operasional & manajemen
- Data master: profil hotel, tipe kamar (foto, fasilitas, harga), kamar, pegawai, dan master shift.
- **Harga dinamis**: aturan harga weekend, musim, atau tanggal tertentu (persen, tambah/kurang nominal, atau harga tetap) dengan prioritas.
- **Kode promo**: persen atau nominal, batas maksimal diskon, minimal malam, masa berlaku, dan kuota.
- Pajak dan service charge bisa diatur.
- **Housekeeping board**: tugas pembersihan, inspeksi, dan perbaikan, lengkap dengan petugas dan riwayat. Kamar kembali dijual setelah tugas selesai.
- **Jadwal shift pegawai** mingguan dan **clock-in/clock-out**, opsional dibatasi IP jaringan hotel dan/atau radius GPS.
- **Laporan hotel**:
  - Okupansi, ADR, RevPAR, pendapatan kamar, dan uang masuk, dengan grafik harian.
  - Rekap per tipe kamar.
- **Rekap shift**: kehadiran, keterlambatan, dan total jam, bisa diekspor ke Excel (CSV).
- Moderasi ulasan dan galeri foto (beberapa foto per tipe kamar, plus foto fasilitas).
- **Audit log** perubahan booking dan reservasi. Kode pintu tidak pernah ikut tercatat.

### Otomatis (scheduler)
- Booking online yang tidak dibayar sampai batas waktu dibatalkan otomatis, dan kamarnya dilepas.
- Tamu yang tidak datang ditandai **no-show**, dan kamarnya dilepas.
- **Backup** harian file unggahan (foto dan bukti transfer) dan database.

### Keamanan
- Security header untuk target grade A: CSP, HSTS di production, X-Frame-Options, Referrer-Policy, Permissions-Policy, dan COOP.
- Rate limiting untuk login, form, dan API. Pemblokiran URL yang berisi pola serangan.
- Callback Tripay diverifikasi dengan **HMAC signature**.
- Bukti transfer disimpan di disk privat dan hanya bisa dibuka staf.
- Kode pintu dienkripsi di database dan otomatis tidak berlaku saat check-out.

---

## Alur utama

```
Tamu: cari kamar ──► pesan (Pending, batas bayar N jam)
                        │
          ┌─────────────┼──────────────────────┐
          ▼             ▼                      ▼
   bayar via Tripay   unggah bukti transfer   tidak bayar
   (callback otomatis)  (dicek front desk)     │
          │             │                      ▼
          └──────► Paid ◄┘              Expired (otomatis)
                    │
         Front desk: Konfirmasi ──► Confirmed
                    │
         Hari H: Check-in (pilih kamar + kode pintu) ──► Menginap
                    │                         │
                    │      folio / perpanjang / pindah kamar
                    ▼
         Check-out (tagihan lunas) ──► Completed ──► tugas housekeeping ──► kamar Available
                    │
         Tamu memberi ulasan

Tidak datang sampai lewat hari check-in ──► NoShow (otomatis)
```

Status booking: `Pending`, `Paid`, `Confirmed`, `Completed`, `Cancelled`, `Expired`, `NoShow`.
Inventaris dijual **per tipe kamar**. Nomor kamar baru ditetapkan saat check-in, dan pengecekan bentrok dikunci di level database (`SELECT … FOR UPDATE`).

---

## Peran & hak akses

| Permission | Superadmin | admin | Pegawai | Customer |
|---|:-:|:-:|:-:|:-:|
| `hms.master`: hotel, kamar, harga, galeri, ulasan, pegawai, shift | ✓ | ✓ | – | – |
| `hms.booking`: reservasi, front desk, kalender | ✓ | ✓ | ✓ | – |
| `hms.housekeeping`: housekeeping board | ✓ | ✓ | ✓ | – |
| `hms.roster`: jadwal shift pegawai | ✓ | ✓ | – | – |
| `hms.my_shift`: clock-in/out | ✓ | ✓ | ✓ | – |
| `hms.reports`: laporan hotel & rekap shift | ✓ | ✓ | – | – |
| User, role & settings sistem | ✓ | – | – | – |
| Website tamu & "Reservasi Saya" | – | – | – | ✓ |

Customer tidak bisa membuka panel admin. Superadmin otomatis memiliki semua izin.

---

## Teknologi

| Bagian | Teknologi |
|---|---|
| Backend | PHP 8.2+ (disarankan 8.3), Laravel 12 |
| Database | PostgreSQL 14+ (wajib, dengan extension `pg_trgm`) |
| Panel admin | Metronic 8 (Bootstrap 5), SweetAlert2, ApexCharts, DataTables |
| Halaman publik | Tailwind CSS v4 via Vite |
| Hak akses | spatie/laravel-permission |
| Audit log | spatie/laravel-activitylog |
| PDF | barryvdh/laravel-dompdf |
| Backup | spatie/laravel-backup |
| Pembayaran | Tripay (HTTP API) |
| Siap untuk | Redis, Laravel Octane, Laravel Reverb (lihat panduan deploy) |

---

## Menjalankan di lokal

Prasyarat: PHP 8.2+ dengan ekstensi `pdo_pgsql`, `gd`, `mbstring`, `intl`, dan `zip`; Composer 2; Node.js 20+; PostgreSQL 14+.

```bash
git clone https://github.com/rendyirawann/hms-sync.git
cd hms-sync

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Buat database `hms_sync` di PostgreSQL, lalu isi `DB_*` di `.env`.

```bash
php artisan migrate --seed      # tabel + role/permission + akun superadmin
php artisan storage:link
npm run build                   # atau `npm run dev` saat mengembangkan

php artisan serve
php artisan queue:work          # terminal lain: kirim email antrean
php artisan schedule:work       # terminal lain: expire booking, no-show, backup
```

- Website tamu: `http://127.0.0.1:8000`
- Panel admin: `http://127.0.0.1:8000/admin/login` (email dari `SUPERADMIN_EMAIL`; password dari `SUPERADMIN_PASSWORD` atau dicetak sekali saat seeding)

Setelah login sebagai admin, isi data awal secara berurutan:
1. **Data Master → Profil Hotel**
2. **Tipe Kamar**
3. **Kamar**
4. **Harga & Promo** (opsional)
5. **Pegawai**
6. **Master Shift**

---

## Konfigurasi penting

Semua ada di `.env`. Contoh lengkapnya di [.env.example](.env.example).

| Variabel | Keterangan |
|---|---|
| `APP_URL` | URL publik. Dipakai di link email dan callback Tripay. |
| `APP_LOCALE=id`, `APP_TIMEZONE=Asia/Jakarta` | Bahasa dan zona waktu. |
| `MAIL_*` | SMTP untuk email aktivasi dan notifikasi tamu. |
| `QUEUE_CONNECTION` | `database` atau `redis`. Email dikirim lewat antrean, jadi **worker wajib jalan**. |
| `TRIPAY_MODE`, `TRIPAY_API_KEY`, `TRIPAY_PRIVATE_KEY`, `TRIPAY_MERCHANT_CODE` | Kredensial Tripay. Kosongkan `TRIPAY_API_KEY` untuk mematikan pembayaran online. |
| `DOOR_LOCK_DRIVER` | `log` (tanpa perangkat). Driver smart lock bisa ditambah lewat `App\Services\DoorLock\DoorLock`. |
| `HMS_NO_SHOW_CUTOFF` | Jam penandaan no-show (default `02:00`). |
| `BACKUP_DISK`, `BACKUP_NOTIFY_EMAIL`, `BACKUP_ARCHIVE_PASSWORD` | Tujuan backup, email notifikasi, dan password zip. |
| `SECURITY_*`, `RATE_LIMIT_*` | Security header dan rate limit (lihat `config/security.php`). |

Pengaturan yang diubah dari panel (**Profil Hotel**) dan tidak lewat `.env`:
- Pajak dan service charge.
- Batas waktu bayar.
- Daftar rekening transfer.
- Lokasi/radius dan IP clock-in.
- Toleransi terlambat.

### Tripay
1. Daftar merchant di [tripay.co.id](https://tripay.co.id), lalu ambil API key, private key, dan kode merchant (sandbox dulu).
2. Isi `TRIPAY_*` di `.env`.
3. Di dashboard Tripay, isi **URL Callback** dengan `https://domain-anda/payment/tripay/callback`.
4. Setelah merchant disetujui, ganti `TRIPAY_MODE=production` beserta kredensial production.

---

## Tugas terjadwal

Didefinisikan di [routes/console.php](routes/console.php). Butuh satu cron di server (lihat panduan deploy).

| Perintah | Jadwal | Fungsi |
|---|---|---|
| `hms:expire-bookings` | tiap 5 menit | Batalkan booking online yang lewat batas bayar (kecuali bukti transfer sedang dicek). |
| `hms:mark-no-shows` | harian `HMS_NO_SHOW_CUTOFF` | Tandai tamu yang tidak datang sebagai no-show. |
| `backup:clean` / `backup:run` / `backup:monitor` | harian 01:00 / 01:30 / 03:00 | Backup file unggahan dan database. |

---

## Testing

Test memakai database PostgreSQL terpisah, **`hms_sync_test`** (lihat `phpunit.xml`), karena aplikasi memakai fitur khusus PostgreSQL.

```bash
createdb hms_sync_test          # sekali saja
php artisan test
```

Cakupan test di `tests/Feature/Hms`:
- Registrasi, aktivasi, dan hak akses per peran.
- Harga, promo, dan aturan harga.
- Pencegahan double booking.
- Booking kedaluwarsa dan no-show.
- Walk-in sampai check-out, folio, perpanjang, dan pindah kamar.
- Enkripsi kode pintu.
- Transfer manual dan callback Tripay (termasuk signature palsu).
- Clock-in dengan IP/GPS dan rekap shift.
- Semua halaman admin bisa dibuka.

---

## Struktur kode

```
app/
  Console/Commands/          hms:expire-bookings, hms:mark-no-shows
  Http/Controllers/
    Backend/Hms/             panel admin HMS (booking, kamar, harga, housekeeping, laporan, ...)
    Customer/                area tamu (reservasi, profil)
    FrontendController.php   landing, pencarian, detail kamar
    TripayCallbackController.php
  Models/                    Booking, Reservation, Room, RoomType, Payment, RateRule, PromoCode, ...
  Notifications/             BookingNotification (email ke tamu, via queue)
  Services/
    BookingService.php       semua perubahan status booking ada di sini
    Pricing.php              harga per malam + promo + service + pajak
    RoomAvailability.php     ketersediaan per tipe kamar
    Tripay.php               integrasi Tripay
    Invoice.php              invoice PDF
    DoorLock/                antarmuka smart lock
config/hms.php               konfigurasi HMS (Tripay, smart lock, no-show)
public/assets/js/hms-admin.js  standar UI admin: SweetAlert, spinner tombol, drawer kanan, modal detail
resources/views/
  backend/hms/               halaman admin HMS
  frontend/                  website tamu
  pdf/invoice.blade.php
tests/Feature/Hms/           feature test
```

Konvensi UI admin (lihat [WORKFLOW.md](WORKFLOW.md)):
- Form tambah/edit memakai komponen `<x-hms.drawer>` (drawer dari kanan) yang dikirim lewat AJAX.
- Hasil setiap aksi ditampilkan dengan SweetAlert, termasuk detail error validasi.
- Tombol submit otomatis menampilkan spinner.
- Halaman detail dibuka di modal tengah lewat `data-hms-show`.

---

## Deploy ke server

Panduan lengkap (Nginx, PHP-FPM atau Octane, Supervisor, cron, Redis, Reverb, HTTPS, backup, dan checklist go-live) ada di **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)**.
