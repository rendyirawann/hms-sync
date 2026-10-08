# Panduan Deploy HMS Sync

Panduan ini memakai **Ubuntu 24.04 LTS** di satu VPS (2 vCPU / 4 GB RAM sudah cukup untuk satu hotel). Contoh domain: `hotel.example.com`. Sesuaikan path dan domain dengan server Anda.

Ringkasan komponen yang berjalan di server:

| Komponen | Fungsi | Dijalankan oleh |
|---|---|---|
| Nginx | Web server + HTTPS + proxy websocket | systemd |
| PHP 8.3-FPM **atau** Laravel Octane | Menjalankan aplikasi | systemd / Supervisor |
| PostgreSQL 16 | Database utama | systemd |
| Redis | Cache, session, queue | systemd |
| Queue worker | Kirim email (aktivasi, notifikasi booking) | Supervisor |
| Laravel Reverb (opsional) | Websocket realtime | Supervisor |
| Cron `schedule:run` | Expire booking, no-show, backup | cron |

---

> **Cara tercepat: Docker.** `docker/compose.yaml` menjalankan app (Octane), queue worker, scheduler, Reverb, Redis, dan PostgreSQL sekaligus. Lihat [bagian 14](#14-alternatif-docker-compose). Bagian 1–13 di bawah adalah instalasi manual di VPS.

## 1. Siapkan server

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx postgresql postgresql-contrib redis-server supervisor git unzip curl

# PHP 8.3 + ekstensi yang dibutuhkan
sudo apt install -y php8.3-fpm php8.3-cli php8.3-pgsql php8.3-redis php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath

# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# Node.js 22 (untuk build asset)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
```

Firewall:

```bash
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
```

## 2. Database PostgreSQL

```bash
sudo -u postgres psql <<'SQL'
CREATE USER hms WITH PASSWORD 'ganti-dengan-password-kuat';
CREATE DATABASE hms_sync OWNER hms;
\c hms_sync
CREATE EXTENSION IF NOT EXISTS pg_trgm;
SQL
```

> `pg_trgm` dipakai untuk index pencarian. Extension ini dibuat dengan user `postgres` karena user aplikasi biasanya tidak punya hak `CREATE EXTENSION`.

## 3. Ambil kode & install dependency

```bash
sudo mkdir -p /var/www && sudo chown $USER:www-data /var/www
cd /var/www
git clone https://github.com/rendyirawann/hms-sync.git
cd hms-sync

composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

## 4. Konfigurasi `.env`

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Nilai minimal untuk production:

```dotenv
APP_NAME="Nama Hotel"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hotel.example.com
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=hms_sync
DB_USERNAME=hms
DB_PASSWORD=ganti-dengan-password-kuat

# Redis untuk cache, session, dan queue
REDIS_CLIENT=phpredis
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

SESSION_SECURE_COOKIE=true
SECURITY_HSTS=true

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=reservasi@hotel.example.com
MAIL_FROM_NAME="${APP_NAME}"

TRIPAY_MODE=production
TRIPAY_API_KEY=...
TRIPAY_PRIVATE_KEY=...
TRIPAY_MERCHANT_CODE=...

BACKUP_NOTIFY_EMAIL=it@hotel.example.com
BACKUP_ARCHIVE_PASSWORD=password-zip-backup

SUPERADMIN_EMAIL=admin@hotel.example.com
SUPERADMIN_PASSWORD=password-awal-yang-kuat
```

> **Jangan pernah commit `.env`.** File ini sudah masuk `.gitignore`.

## 5. Migrasi, seed, dan optimasi

```bash
php artisan migrate --force
php artisan db:seed --force          # role, permission, akun superadmin
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Setelah login pertama, **ganti password superadmin** lalu hapus `SUPERADMIN_PASSWORD` dari `.env`.

## 6. Pilih cara menjalankan PHP

### Opsi A: PHP-FPM (paling sederhana)

`/etc/nginx/sites-available/hms-sync`:

```nginx
server {
    listen 80;
    server_name hotel.example.com;
    root /var/www/hms-sync/public;
    index index.php;

    client_max_body_size 12M;   # unggah foto & bukti transfer
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Asset hasil build Vite memakai hash di nama file
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header X-Content-Type-Options "nosniff";
    }

    location ~* \.(css|js|woff2?|ttf|svg|png|jpe?g|gif|webp|ico)$ {
        expires 7d;
        add_header X-Content-Type-Options "nosniff";
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known) { deny all; }

    gzip on;
    gzip_types text/plain text/css application/javascript application/json image/svg+xml;

    server_tokens off;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/hms-sync /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Matikan `expose_php` di `/etc/php/8.3/fpm/php.ini` (`expose_php = Off`), lalu `sudo systemctl restart php8.3-fpm`.

### Opsi B: Laravel Octane (lebih cepat)

`config/octane.php` sudah ada di repo (`.rr.yaml` dibuat otomatis oleh Octane). Unduh binary RoadRunner sekali di server:

```bash
php vendor/bin/rr get-binary          # menaruh ./rr di root project (sudah di .gitignore)
```

> Octane membutuhkan ekstensi `pcntl`, jadi hanya berjalan di Linux/macOS (atau container), **tidak** di PHP Windows/XAMPP. Untuk pengembangan di Windows tetap pakai `php artisan serve`.

Tambahkan di `.env`:

```dotenv
OCTANE_SERVER=roadrunner
OCTANE_HTTPS=true
```

Program Supervisor `/etc/supervisor/conf.d/hms-octane.conf`:

```ini
[program:hms-octane]
command=php /var/www/hms-sync/artisan octane:start --server=roadrunner --host=127.0.0.1 --port=8000 --workers=auto --max-requests=500
directory=/var/www/hms-sync
user=www-data
autostart=true
autorestart=true
stopwaitsecs=30
redirect_stderr=true
stdout_logfile=/var/www/hms-sync/storage/logs/octane.log
```

Bagian `location /` dan `location ~ \.php$` di Nginx diganti menjadi proxy:

```nginx
    location / {
        try_files $uri @octane;
    }

    location @octane {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_pass http://127.0.0.1:8000;
    }
```

Karena aplikasi berada di belakang proxy, isi `TRUSTED_PROXIES=127.0.0.1` di `.env` agar IP tamu (rate limit, clock-in IP) terbaca benar.

> Kode HMS sudah aman untuk Octane: data per request memakai `once()`/binding `scoped`, bukan variabel `static`. Setiap deploy wajib menjalankan `php artisan octane:reload`.

## 7. Queue worker & Reverb (Supervisor)

`/etc/supervisor/conf.d/hms-worker.conf`:

```ini
[program:hms-worker]
command=php /var/www/hms-sync/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=/var/www/hms-sync
user=www-data
numprocs=2
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/hms-sync/storage/logs/worker.log
```

**Reverb (opsional, untuk realtime).** Paket `laravel/reverb` sudah terpasang. Listener paksa-logout di panel admin sudah memakainya. Event realtime HMS (booking baru, status kamar) bisa ditambah di atas fondasi ini.

```bash
php artisan reverb:install
```

Isi di `.env`:

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...            # dibuat oleh reverb:install
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
# Dipakai PHP untuk mengirim event ke Reverb (alamat internal)
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
# Dipakai browser (alamat publik di balik Nginx + HTTPS)
VITE_REVERB_HOST=hotel.example.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Panel admin dan halaman tamu memuat Echo dari asset lokal dan membaca `VITE_REVERB_*` saat runtime, jadi tidak perlu build ulang. Event realtime dikirim lewat **queue**, jadi worker wajib jalan.

Yang realtime:
- **Front desk** (channel privat `hms.frontdesk`): reservasi baru, bukti transfer, pembayaran, konfirmasi/batal, check-in/out, perpanjang, dan pindah kamar.
- **Housekeeping** (`hms.housekeeping`): perubahan status kamar dan tugas.
- **Tamu** (`App.Models.User.{id}`): halaman reservasi ter-update saat statusnya berubah. Payload tidak pernah berisi kode pintu.

`/etc/supervisor/conf.d/hms-reverb.conf`:

```ini
[program:hms-reverb]
command=php /var/www/hms-sync/artisan reverb:start --host=127.0.0.1 --port=8080
directory=/var/www/hms-sync
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/hms-sync/storage/logs/reverb.log
```

Proxy websocket di Nginx (di dalam blok `server`):

```nginx
    location /app {
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_pass http://127.0.0.1:8080;
    }
```

Aktifkan semua program:

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
```

## 8. Scheduler (cron)

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/hms-sync && php artisan schedule:run >> /dev/null 2>&1
```

Cek jadwal: `php artisan schedule:list`. Isinya expire booking (tiap 5 menit), no-show (harian), dan backup (harian).

## 9. HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d hotel.example.com
```

Setelah HTTPS aktif, pastikan `.env` berisi `SESSION_SECURE_COOKIE=true` dan `SECURITY_HSTS=true`, lalu jalankan `php artisan config:cache`.

Cek grade header di [securityheaders.com](https://securityheaders.com). Targetnya **A**. Nilai A+ tidak bisa dicapai karena CSP masih butuh `unsafe-inline` untuk script tema.

## 10. Tripay

1. Di dashboard Tripay, isi **URL Callback**: `https://hotel.example.com/payment/tripay/callback`.
2. Uji di mode sandbox dulu (`TRIPAY_MODE=sandbox` + kredensial sandbox): buat booking, bayar lewat simulator Tripay, dan pastikan status booking berubah menjadi **Sudah dibayar**.
3. Ganti ke kredensial production dan `TRIPAY_MODE=production`, lalu jalankan `php artisan config:cache`.

Callback diverifikasi dengan HMAC (`X-Callback-Signature`). Request dengan signature salah ditolak 403 dan dicatat di log.

## 11. Backup

Backup memakai `spatie/laravel-backup` (`config/backup.php`). Isinya:
- Dump database (butuh `pg_dump`, sudah terpasang bersama PostgreSQL).
- `storage/app/public` (foto).
- `storage/app/private/payments` (bukti transfer).

- Default disimpan di disk `local` (`storage/app/private/<APP_NAME>`). **Sangat disarankan** memakai disk eksternal, misalnya S3/R2/Wasabi: isi `AWS_*` di `.env`, lalu `BACKUP_DISK=s3`.
- Uji manual: `php artisan backup:run` lalu `php artisan backup:list`.
- Restore database: `pg_restore` / `psql hms_sync < dump.sql` dari file zip backup.

## 12. Update / deploy ulang

Simpan sebagai `deploy.sh` di server:

```bash
#!/usr/bin/env bash
set -e
cd /var/www/hms-sync

php artisan down --retry=15
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan queue:restart
php artisan octane:reload 2>/dev/null || sudo systemctl reload php8.3-fpm
php artisan up
```

## 13. Checklist go-live

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` memakai https.
- [ ] HTTPS aktif, `SESSION_SECURE_COOKIE=true`, `SECURITY_HSTS=true`.
- [ ] Password superadmin sudah diganti; `SUPERADMIN_PASSWORD` dihapus dari `.env`.
- [ ] SMTP diuji: daftar akun baru, lalu email aktivasi diterima.
- [ ] Queue worker berjalan (`supervisorctl status`) dan email notifikasi booking terkirim.
- [ ] Cron scheduler aktif (`schedule:list`), lalu uji `php artisan hms:expire-bookings`.
- [ ] Tripay production aktif dan URL callback terdaftar. Satu transaksi kecil sudah diuji.
- [ ] Profil Hotel lengkap: alamat, jam check-in/out, pajak, service charge, batas bayar, rekening transfer.
- [ ] Tipe kamar, foto, kamar, dan harga sudah diisi. Pegawai dan shift sudah dibuat.
- [ ] Backup diuji (`backup:run`) dan disimpan ke disk eksternal.
- [ ] `php artisan test` hijau di lingkungan staging.
- [ ] Grade header di securityheaders.com = A.
- [ ] Realtime: buka halaman Reservasi di satu browser, buat booking dari browser lain, lalu pastikan notifikasi muncul.
- [ ] Smart lock (jika dipakai): satu check-in uji dan kodenya membuka pintu; check-out menghapus kode.

## 14. Alternatif: Docker Compose

Prasyarat: Docker Engine 24+ (atau Podman 5+) di server Linux.

```bash
git clone https://github.com/rendyirawann/hms-sync.git && cd hms-sync
cp .env.example .env
# isi APP_KEY (php artisan key:generate --show), APP_URL, DB_PASSWORD, MAIL_*, TRIPAY_*,
# REVERB_APP_ID/KEY/SECRET (bebas, string acak), VITE_REVERB_HOST=domain-anda, dst.

docker compose -f docker/compose.yaml up -d --build
docker compose -f docker/compose.yaml exec app php artisan migrate --force
docker compose -f docker/compose.yaml exec app php artisan db:seed --force
```

Container yang berjalan:

| Service | Isi |
|---|---|
| `app` | Octane (RoadRunner) di `127.0.0.1:8000` |
| `worker` | `queue:work redis` (email + event realtime) |
| `scheduler` | `schedule:work` (expire booking, no-show, backup) |
| `reverb` | websocket di `127.0.0.1:8080` |
| `redis` | cache, session, queue |
| `postgres` | PostgreSQL 16 (data di volume `postgres`) |

File unggahan (foto, bukti transfer) disimpan di volume `storage`. Pasang Nginx/Caddy dengan HTTPS di depan port 8000 (app) dan 8080 (path `/app` untuk websocket), sama seperti contoh Nginx di bagian 6B dan 7.

Deploy ulang:

```bash
git pull
docker compose -f docker/compose.yaml up -d --build
docker compose -f docker/compose.yaml exec app php artisan migrate --force
```

> Image ini sudah diuji: UAT browser lengkap (51 skenario) dan uji realtime end-to-end berjalan di atas container Octane + Redis + PostgreSQL + Reverb.

