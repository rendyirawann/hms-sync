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
- [x] **Database Foundation:** Skema Hotels, RoomTypes, Rooms, Shifts, Bookings, Reservations, EmployeeShifts (catatan: hanya `users` yang UUID; tabel HMS memakai ID auto-increment).
- [x] **Frontend UI:** Landing page Tailwind CSS v4, kini memakai data hotel & tipe kamar dari database.
- [x] **Layout Admin:** Diganti ke versi template `startertempalt` (app-shell, security headers, rate limit, branding).
- [x] **Standarisasi UI Backend:** `public/assets/js/hms-admin.js` — SweetAlert global (termasuk detail error validasi), spinner di setiap tombol submit, form Tambah/Edit sebagai drawer kanan (`<x-hms.drawer>`), detail di modal tengah (`data-hms-show`).
- [x] **Sistem Autentikasi Customer:** Register → email aktivasi → login (akun belum aktif ditolak, ada kirim ulang aktivasi). Role `Customer` tidak bisa masuk panel admin.
- [x] **Modul Manajemen Kamar:** Profil hotel, CRUD Tipe Kamar (foto, harga, fasilitas), CRUD Kamar + status housekeeping.
- [x] **Modul Booking Engine:** Cek ketersediaan per tipe kamar (anti double-booking), buat reservasi, "Reservasi Saya", batal oleh tamu (status Pending).
- [x] **Reservasi & Check-in (admin):** Tandai lunas, konfirmasi, batal, check-in (pilih kamar + passcode pintu 6 digit otomatis), passcode ulang, check-out (kamar → Cleaning).
- [x] **Modul Pegawai & Shift:** CRUD Pegawai, Master Shift, Jadwal mingguan, Shift Saya (clock-in/clock-out).
- [ ] **Pembayaran online** (payment gateway) — saat ini pelunasan ditandai manual oleh admin.
- [ ] **Notifikasi email** ke tamu saat booking dikonfirmasi / check-in (berisi passcode).
- [ ] **Booking walk-in** dibuat langsung oleh resepsionis dari panel admin.
- [ ] **Laporan** okupansi & pendapatan di dashboard.

## 6. Hak Akses HMS
| Permission | Superadmin | admin | Pegawai | Customer |
|---|---|---|---|---|
| `hms.master` (hotel, kamar, pegawai, shift) | ✓ | ✓ | – | – |
| `hms.booking` (reservasi & front desk) | ✓ | ✓ | ✓ | – |
| `hms.roster` (jadwal pegawai) | ✓ | ✓ | – | – |
| `hms.my_shift` (clock-in/out) | ✓ | ✓ | ✓ | – |

---
*Dokumen ini dapat diperbarui seiring berjalannya proses pengembangan proyek HMS.*
