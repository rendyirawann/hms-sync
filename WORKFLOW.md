# Alur Kerja dan Workflow HMS (Hotel Management System)

Dokumen ini merangkum alur kerja, spesifikasi sistem, dan daftar tugas (roadmap) untuk pengembangan proyek HMS berdasarkan permintaan dan diskusi kita. Sistem ini dirancang khusus untuk operasional satu hotel (bukan SAAS).

## 1. Arsitektur Pengguna (Role & Permissions)
Sistem memiliki beberapa tingkatan akses pengguna:
- **Superadmin:** Mengelola seluruh konfigurasi sistem, role, dan akses tingkat atas.
- **Admin:** Mengelola operasional inti hotel, konfirmasi reservasi, dan manajemen data.
- **Pegawai (Staff):** Menjalankan operasional harian, absensi, dan manajemen shift.
- **Customer:** Pengguna publik yang mendaftar untuk melakukan pemesanan kamar.

## 2. Alur Pengguna Publik (Customer Workflow)
1. **Eksplorasi (Frontend Landing Page):** 
   - Customer dapat melihat-lihat tipe kamar, fasilitas, dan penawaran hotel melalui halaman depan (sudah diselesaikan dengan desain ala Traveloka/Agoda).
2. **Registrasi & Aktivasi Akun:**
   - Customer wajib mendaftarkan akun menggunakan alamat email.
   - Sistem akan mengirimkan link aktivasi ke email tersebut.
   - Setelah aktivasi berhasil, customer baru dapat melakukan Login.
3. **Proses Reservasi (Booking):**
   - Customer login dan mencari ketersediaan kamar.
   - Customer membuat reservasi berdasarkan data master kamar yang tersedia.
   - Customer dapat melihat status reservasi mereka.

## 3. Alur Operasional Internal (Backend/Admin Workflow)
1. **Manajemen Data Master (CRUD):** 
   - Mengelola data Profil Hotel.
   - Mengelola Tipe Kamar dan Data Kamar.
   - Mengelola data Pegawai/Staff.
2. **Manajemen Shift Pegawai:** 
   - Penetapan jadwal shift.
   - Fitur bagi pegawai untuk memulai/mengakhiri shift kerja mereka.
3. **Manajemen Reservasi & Check-in:**
   - Admin memantau reservasi yang masuk dari frontend.
   - Admin melakukan konfirmasi atau pembatalan booking.
   - Proses Check-in: Termasuk (seperti yang pernah dibahas) men-generate *passcode* pintu digital secara otomatis untuk tamu.

## 4. Standar UI/UX Backend (Wajib Diimplementasikan)
Untuk menjaga konsistensi dan pengalaman pengguna yang modern di area dashboard admin, sistem harus menerapkan aturan berikut:
1. **Notifikasi Global (SweetAlert):**
   - Sistem harus menggunakan SweetAlert secara global untuk semua respon proses data (Berhasil/Gagal).
   - Jika terjadi error validasi (misal input kosong), SweetAlert harus menampilkan detail pesan error tersebut kepada pengguna.
2. **Loading Spinner pada Form:**
   - Setiap tombol aksi (Simpan / Proses / Submit) di seluruh form harus dilengkapi dengan efek *loading spinner* ketika diklik, untuk mencegah klik ganda dan memberikan respon visual.
3. **Standarisasi Animasi Modal:**
   - **Modal Form (Tambah & Edit):** Harus muncul dengan animasi bergeser (slide-in) dari sebelah **kanan** layar (model *offcanvas/drawer*).
   - **Modal Detail (Show/Lihat):** Harus tetap muncul secara klasik di **tengah** layar (Centered modal).

## 5. Roadmap & Status Pengerjaan
- [x] Database foundation, landing page, layout admin (template startertempalt), standar UI backend.
- [x] Autentikasi customer: registrasi, aktivasi email, kirim ulang aktivasi, profil customer.
- [x] Manajemen kamar: profil hotel, tipe kamar, kamar, galeri foto.
- [x] Booking engine: ketersediaan per tipe kamar (anti double-booking), harga dinamis, kode promo, pajak & service.
- [x] Pembayaran: Tripay (callback ber-signature) + transfer manual dengan verifikasi + tunai di front desk.
- [x] Otomatis: booking tidak dibayar kedaluwarsa, no-show, backup harian.
- [x] Front desk: walk-in, konfirmasi, check-in + kode pintu terenkripsi, folio, perpanjang, pindah kamar, check-out, invoice PDF.
- [x] Housekeeping board, kalender okupansi.
- [x] Pegawai & shift: jadwal, clock-in/out dengan batas IP/GPS, rekap kehadiran (CSV).
- [x] Laporan: okupansi, ADR, RevPAR, pendapatan.
- [x] Ulasan tamu, notifikasi email (queue), audit log, test otomatis, git.
- [ ] Driver smart lock vendor (TTLock/Nuki) — antarmuka `App\Services\DoorLock\DoorLock` sudah siap.
- [ ] Event realtime via Reverb (booking baru / status kamar) — fondasi Reverb sudah terpasang.
- [ ] Migrasi infrastruktur ke Redis + Octane (lihat docs/DEPLOYMENT.md).

## 6. Hak Akses HMS
| Permission | Superadmin | admin | Pegawai | Customer |
|---|---|---|---|---|
| `hms.master` (hotel, kamar, pegawai, shift) | ✓ | ✓ | – | – |
| `hms.booking` (reservasi & front desk) | ✓ | ✓ | ✓ | – |
| `hms.roster` (jadwal pegawai) | ✓ | ✓ | – | – |
| `hms.my_shift` (clock-in/out) | ✓ | ✓ | ✓ | – |

---
*Dokumen ini dapat diperbarui seiring berjalannya proses pengembangan proyek HMS.*
