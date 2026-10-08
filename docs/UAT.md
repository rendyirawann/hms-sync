# Laporan UAT — HMS Sync

- **Tanggal:** 9 Oktober 2026
- **Lingkungan:** server lokal (`php artisan serve`), database PostgreSQL terpisah `hms_sync_uat` berisi data demo (`DemoSeeder`), email ke log, queue sync.
- **Metode:** browser sungguhan (Microsoft Edge headless lewat puppeteer) menjalankan alur seperti pengguna: klik tombol, isi drawer, SweetAlert, unggah file. Setiap request, error console, pelanggaran CSP, dan asset yang gagal dimuat ikut dicatat.
- **Hasil:** **58 / 58 skenario lulus.** Tidak ada asset gagal dimuat dan tidak ada pelanggaran CSP. Respons 403/422 yang tercatat berasal dari skenario negatif yang disengaja.
- **Test otomatis:** `php artisan test`, 27 test / 165 assertion lulus.

Akun demo (`php artisan db:seed --class=DemoSeeder`, password `Password123!`):
- `admin@nusantara.test` (admin)
- `rina@nusantara.test` (pegawai)
- `budi@tamu.test` (tamu)

## Hasil per skenario

### Publik

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Landing page terbuka | ✅ |  |

### Keamanan

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Header keamanan ada (CSP, XFO, nosniff, Referrer, Permissions, COOP) | ✅ | content-security-policy, cross-origin-opener-policy, cross-origin-resource-policy, permissions-policy, referrer-policy, x-content-type-optio |
| X-Powered-By tidak bocor | ✅ |  |

### Performa

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Landing tidak memuat JS berat | ✅ |  |

### Publik

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Halaman detail kamar terbuka | ✅ |  |
| Pencarian ketersediaan menampilkan kamar | ✅ | 3 tipe tersedia |
| Validasi tanggal lampau berbahasa Indonesia | ✅ | Tanggal check-in harus tanggal setelah atau sama dengan today. |

### Registrasi

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Daftar akun → diarahkan ke login dengan pesan cek email | ✅ |  |
| Login sebelum aktivasi ditolak | ✅ |  |
| Email aktivasi terkirim (mail log) | ✅ |  |
| Klik link aktivasi → akun aktif | ✅ |  |
| Login setelah aktivasi → Reservasi Saya | ✅ | http://127.0.0.1:8950/reservasi?welcome=1 |

### Hak akses

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Customer tidak bisa buka panel admin | ✅ | HTTP 403 |

### Booking tamu

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Kode promo HEMAT10 dicek & diskon tampil | ✅ |  |
| Reservasi dibuat & masuk halaman pembayaran | ✅ | HMS-261009-U1FIQ |
| Unggah bukti transfer | ✅ |  |
| Unduh invoice PDF | ✅ | application/pdf |
| Reservasi kedua (menginap hari ini) dibuat | ✅ | HMS-261009-JMF3L |
| Tamu membatalkan reservasi Pending | ✅ |  |
| Halaman profil tamu terbuka | ✅ |  |

### Front desk

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Admin login → dashboard | ✅ | http://127.0.0.1:8950/admin/dashboard |
| Penanda "bukti transfer perlu dicek" muncul | ✅ | jumlah: 1 |
| Terima bukti transfer → lunas | ✅ | Bukti transfer diterima. Booking HMS-261009-U1FIQ ditandai lunas. |
| Konfirmasi booking | ✅ | Booking HMS-261009-U1FIQ berhasil dikonfirmasi. |
| Catat pembayaran tunai | ✅ | Pembayaran Rp 659.340 dicatat untuk HMS-261009-JMF3L. |
| Check-in → kode pintu 6 digit dibuat | ✅ | Check-in berhasil. Kamar 101, passcode pintu: 864353 |
| Tambah tagihan folio (minibar) | ✅ | Tagihan ditambahkan ke folio. |
| Perpanjang menginap 1 malam | ✅ | Masa inap diperpanjang. Tagihan tambahan Rp 659.340 masuk ke folio. |
| Pindah kamar | ✅ | Tamu dipindah ke kamar 102. Passcode tetap sama. |
| Check-out ditolak karena masih ada tagihan | ✅ | Masih ada tagihan Rp 689.340. Catat pembayaran dulu sebelum check-out. |
| Check-out setelah lunas | ✅ | Check-out berhasil. Kamar 102 masuk antrean housekeeping. |
| Booking walk-in via drawer kanan | ✅ | Booking walk-in HMS-261009-9FUZR dibuat. Lanjutkan pembayaran & check-in dari tombol Kelola. |

### UI standar

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Error validasi tampil detail di SweetAlert | ✅ | Tipe kamar wajib diisi. / Nama tamu wajib diisi. / Telepon wajib diisi. |

### Housekeeping

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Tugas bersih otomatis dibuat setelah check-out/pindah kamar | ✅ | 2 tugas |
| Selesaikan tugas → kamar siap | ✅ | Kamar 101 selesai dikerjakan. |

### Halaman admin

| Skenario | Hasil | Catatan |
|---|:-:|---|
| kalender terbuka | ✅ | HTTP 200 |
| laporan terbuka | ✅ | HTTP 200 |
| rekap-shift terbuka | ✅ | HTTP 200 |
| harga-promo terbuka | ✅ | HTTP 200 |
| galeri terbuka | ✅ | HTTP 200 |
| ulasan terbuka | ✅ | HTTP 200 |
| profil-hotel terbuka | ✅ | HTTP 200 |
| kamar terbuka | ✅ | HTTP 200 |
| tipe-kamar terbuka | ✅ | HTTP 200 |
| pegawai terbuka | ✅ | HTTP 200 |
| jadwal terbuka | ✅ | HTTP 200 |

### Master data

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Tambah kode promo | ✅ | Kode promo ditambahkan. |
| Tambah tipe kamar dengan foto | ✅ | Tipe kamar berhasil ditambahkan. |
| Unggah foto fasilitas | ✅ | 1 foto fasilitas ditambahkan. |

### Shift

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Admin mengatur jadwal shift pegawai | ✅ | Jadwal shift berhasil disimpan. |

### Responsif

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Panel admin di HP tanpa scroll horizontal halaman | ✅ |  |

### Pegawai

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Clock-in | ✅ | Shift dimulai pukul 00:18. Selamat bekerja! |
| Clock-out | ✅ | Shift selesai pukul 00:18. Terima kasih! |

### Hak akses

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Pegawai tidak bisa buka master Kamar | ✅ | HTTP 403 |
| Pegawai bisa buka Reservasi | ✅ |  |

### Ulasan

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Tamu memberi ulasan setelah check-out | ✅ |  |
| Ulasan tampil di landing | ✅ |  |

### Responsif

| Skenario | Hasil | Catatan |
|---|:-:|---|
| Landing di HP tanpa scroll horizontal | ✅ |  |

## Temuan selama UAT (sudah diperbaiki)

| Temuan | Perbaikan |
|---|---|
| Audit log membuat transaksi booking gagal (`activity_log.subject_id` bertipe UUID, sedangkan ID booking integer) | Kolom diubah ke varchar (migration `widen_activity_log_subject_id`) |
| Branding halaman login/daftar masih "StarterTemp / Modern Admin Dashboard" | Default branding diganti ke HMS (migration `hms_branding_defaults`); teks panel login disesuaikan |
| Pesan validasi campur bahasa Inggris ("The nama field…", "…today") | File bahasa `lang/id` ditambahkan, `APP_LOCALE=id`, pesan tanggal check-in dibuat khusus |
| Error console "accelerometer is not allowed" di halaman login (animasi latar p5.js dari template) | Permissions-Policy mengizinkan accelerometer/gyroscope untuk origin sendiri saja |
| Data hotel tidak langsung terbaca setelah disimpan dalam proses yang sama (memo `once()`) | Memo di-reset otomatis saat profil hotel disimpan |
| Nama hotel panjang terpotong di header website | Lebar maksimal nama dibuat responsif |

## Belum diuji di UAT ini

- **Pembayaran Tripay sungguhan:** butuh kredensial sandbox. Alurnya (pembuatan tagihan + callback dengan signature, termasuk penolakan signature palsu) sudah diuji di `tests/Feature/Hms/PaymentTest.php` dengan respons Tripay tiruan. Lakukan satu transaksi di sandbox Tripay sebelum go-live.
- **Pengiriman email SMTP sungguhan:** di UAT, email ditulis ke log. Isi email aktivasi berhasil diverifikasi.
- **Clock-in dengan GPS** di perangkat sungguhan (izin lokasi browser). Logika radius dan IP sudah diuji di test otomatis.
- **Login sosial** (Google dan lainnya): belum dikonfigurasi.
