# Deploy HMS Sync ke Subdomain (Server yang Sudah Berisi Project Lain)

Panduan ini untuk memasang HMS Sync di **subdomain** (contoh: `hms.domainanda.com`) pada VPS yang **sudah menjalankan project lain**, dengan Nginx, Laravel Octane (RoadRunner), Redis, queue worker, dan Reverb, **tanpa mengganggu project yang sudah ada**.

> Untuk server baru yang masih kosong, lihat [DEPLOYMENT.md](DEPLOYMENT.md).

Prinsip utamanya: **semua milik HMS diberi nama, port, database, dan prefix sendiri.** Jangan mengubah file konfigurasi project lain, server block default Nginx, atau konfigurasi global Redis/PHP.

Ganti nilai berikut sesuai server Anda:

| Placeholder | Contoh | Keterangan |
|---|---|---|
| `hms.domainanda.com` | `hms.club100.com` | subdomain HMS |
| `/var/www/hms-sync` | | folder aplikasi |
| `8010` | | port Octane (localhost saja) |
| `8091` | | port Reverb (localhost saja) |
| `5` / `6` | | nomor database Redis untuk HMS |

---

## 1. Cek kondisi server dulu (hanya membaca, tidak mengubah apa pun)

```bash
# Port yang sudah dipakai. Pilih port Octane & Reverb yang TIDAK muncul di sini.
sudo ss -tlnp

# Site Nginx yang sudah ada
ls -l /etc/nginx/sites-enabled/
sudo nginx -t                      # pastikan konfigurasi saat ini sudah valid SEBELUM menambah apa pun

# Program Supervisor yang sudah ada (nama program HMS tidak boleh bentrok)
sudo supervisorctl status

# Database Redis yang sudah terpakai (db0, db1, ...)
redis-cli INFO keyspace

# Versi PHP & ekstensi yang dibutuhkan
php -v
php -m | grep -E "pdo_pgsql|redis|pcntl|sockets|gd|intl|zip|bcmath"

# PostgreSQL
sudo -u postgres psql -c "\l"
```

Catat:
- Port bebas untuk Octane (misal `8010`) dan Reverb (misal `8091`). Cek ulang: `sudo ss -tlnp | grep -E ':8010|:8091'` harus kosong.
- Nomor DB Redis yang belum dipakai (misal `5` dan `6`).

Jika ekstensi PHP kurang, pasang hanya ekstensinya (tidak mengubah versi PHP project lain):

```bash
sudo apt install php8.3-pgsql php8.3-redis php8.3-gd php8.3-intl php8.3-zip php8.3-bcmath php8.3-mbstring php8.3-xml php8.3-curl
```

> Octane (RoadRunner) membutuhkan `pcntl` dan `sockets`. Di PHP CLI Ubuntu keduanya sudah aktif secara default.

---

## 2. DNS

Di panel DNS domain, tambahkan:

| Type | Name | Value |
|---|---|---|
| A | `hms` | IP publik VPS |

Tunggu propagasi, lalu cek: `dig +short hms.domainanda.com` harus menampilkan IP VPS.

---

## 3. Database PostgreSQL sendiri

Buat user dan database **khusus HMS**. Database project lain tidak disentuh.

```bash
sudo -u postgres psql <<'SQL'
CREATE USER hms_sync WITH PASSWORD 'GANTI_PASSWORD_KUAT';
CREATE DATABASE hms_sync OWNER hms_sync;
REVOKE ALL ON DATABASE hms_sync FROM PUBLIC;
SQL
```

---

## 4. Ambil kode & install

```bash
sudo mkdir -p /var/www/hms-sync
sudo chown $USER:www-data /var/www/hms-sync
git clone https://github.com/rendyirawann/hms-sync.git /var/www/hms-sync
cd /var/www/hms-sync

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate
```

---

## 5. `.env`: kunci isolasi dari project lain

Bagian yang **wajib unik** ditandai ⚠️.

```dotenv
APP_NAME="HMS Sync"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hms.domainanda.com
APP_LOCALE=id

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=hms_sync          # ⚠️ database sendiri
DB_USERNAME=hms_sync
DB_PASSWORD=GANTI_PASSWORD_KUAT

# --- Redis: dipakai bersama project lain, jadi DIPISAH lewat nomor DB + prefix ---
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null           # isi jika Redis server memakai password
REDIS_DB=5                    # ⚠️ queue, lock, broadcast
REDIS_CACHE_DB=6              # ⚠️ cache
REDIS_PREFIX=hms_sync_        # ⚠️ prefix semua key Redis
CACHE_PREFIX=hms_sync_cache_  # ⚠️

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_QUEUE=hms               # ⚠️ nama antrean sendiri

# --- Cookie sesi hanya untuk subdomain ini ---
SESSION_COOKIE=hms_sync_session   # ⚠️ beda nama dari cookie project lain
SESSION_DOMAIN=hms.domainanda.com # JANGAN ".domainanda.com" (bisa bentrok dengan project di domain utama)
SESSION_SECURE_COOKIE=true

# --- Octane ---
OCTANE_SERVER=roadrunner
OCTANE_HTTPS=true

# --- Reverb (realtime) ---
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=hms-sync
REVERB_APP_KEY=GANTI_KEY_ACAK
REVERB_APP_SECRET=GANTI_SECRET_ACAK
REVERB_SERVER_HOST=127.0.0.1      # hanya localhost, publik lewat Nginx
REVERB_SERVER_PORT=8091           # ⚠️ port bebas dari langkah 1
REVERB_HOST=hms.domainanda.com    # alamat yang dipakai browser
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# --- Superadmin awal ---
SUPERADMIN_EMAIL=admin@domainanda.com
SUPERADMIN_PASSWORD=GANTI_PASSWORD_KUAT

# --- Tripay, mail, dll: lihat DEPLOYMENT.md ---
```

Buat key acak Reverb: `openssl rand -hex 16` (key) dan `openssl rand -hex 32` (secret).

Kenapa perlu begini:
- **Redis dipakai bersama.** Tanpa nomor DB dan prefix sendiri, key cache, sesi, dan antrean bisa tertimpa project lain. Yang lebih berbahaya, `php artisan cache:clear` di salah satu project bisa menghapus cache project lain (`FLUSHDB` berlaku untuk seluruh DB yang sedang dipakai).
- **Nama antrean sendiri** (`REDIS_QUEUE=hms`) mencegah worker project lain mengambil job HMS, dan sebaliknya.
- **Cookie sesi** dengan nama dan domain sendiri mencegah login HMS menimpa login project lain di domain yang sama.

Setelah `.env` terisi, build ulang aset (variabel `VITE_*` ikut dibundel) lalu siapkan aplikasi:

```bash
npm run build
php artisan migrate --force
php artisan db:seed --force            # role, permission, superadmin. JANGAN jalankan DemoSeeder di produksi
php artisan storage:link
php artisan octane:install --server=roadrunner   # mengunduh binary rr ke folder project
php artisan optimize

sudo chown -R $USER:www-data /var/www/hms-sync
sudo chmod -R ug+rwX storage bootstrap/cache
```

---

## 6. Supervisor: Octane, worker, Reverb

Satu file khusus HMS. Semua program diberi awalan `hms-` supaya tidak bentrok dengan program lain.

`/etc/supervisor/conf.d/hms-sync.conf`:

```ini
[program:hms-octane]
command=php /var/www/hms-sync/artisan octane:start --server=roadrunner --host=127.0.0.1 --port=8010 --workers=4 --max-requests=500
directory=/var/www/hms-sync
user=www-data
autostart=true
autorestart=true
stopwaitsecs=30
redirect_stderr=true
stdout_logfile=/var/www/hms-sync/storage/logs/octane.log

[program:hms-worker]
command=php /var/www/hms-sync/artisan queue:work redis --queue=hms --sleep=3 --tries=3 --max-time=3600
directory=/var/www/hms-sync
user=www-data
numprocs=1
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/hms-sync/storage/logs/worker.log

[program:hms-reverb]
command=php /var/www/hms-sync/artisan reverb:start --host=127.0.0.1 --port=8091
directory=/var/www/hms-sync
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/hms-sync/storage/logs/reverb.log

[group:hms]
programs=hms-octane,hms-worker,hms-reverb
```

Muat **hanya** program baru. Jangan pakai `supervisorctl reload` atau `restart all`, karena keduanya ikut me-restart project lain:

```bash
sudo supervisorctl reread
sudo supervisorctl update          # hanya menambah/mengubah program yang berubah
sudo supervisorctl status hms:*
curl -I http://127.0.0.1:8010      # harus HTTP 200/302
```

> Atur `--workers` sesuai RAM. Setiap worker Octane memakan sekitar 60–120 MB, jadi sisakan memori untuk project lain.

---

## 7. Nginx: server block khusus subdomain

Buat file baru. File site lain dan `default` **tidak diubah**.

`/etc/nginx/sites-available/hms.domainanda.com`:

```nginx
map $http_upgrade $hms_connection_upgrade {
    default upgrade;
    ''      close;
}

upstream hms_octane { server 127.0.0.1:8010; keepalive 16; }
upstream hms_reverb { server 127.0.0.1:8091; }

server {
    listen 80;
    listen [::]:80;
    server_name hms.domainanda.com;

    root /var/www/hms-sync/public;
    index index.php;
    charset utf-8;
    client_max_body_size 20M;

    access_log /var/log/nginx/hms-sync.access.log;
    error_log  /var/log/nginx/hms-sync.error.log;

    # Header keamanan dikirim oleh aplikasi (SecurityHeaders middleware). Jangan diduplikasi di sini.

    # Aset statis dilayani langsung oleh Nginx
    location ~* \.(?:css|js|mjs|map|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|eot|webmanifest)$ {
        try_files $uri @octane;
        expires 30d;
        access_log off;
        add_header Cache-Control "public, max-age=2592000";
    }

    location = /favicon.ico { access_log off; log_not_found off; try_files $uri @octane; }
    location = /robots.txt  { access_log off; log_not_found off; try_files $uri @octane; }

    # WebSocket Reverb
    location /app {
        proxy_pass http://hms_reverb;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $hms_connection_upgrade;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 120s;
    }

    location / {
        try_files $uri @octane;
    }

    location @octane {
        proxy_pass http://hms_octane;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        proxy_read_timeout 60s;
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

Catatan penamaan: `map`, `upstream`, dan variabel diberi awalan `hms_` karena ketiganya berlaku global di Nginx. Kalau project lain sudah punya `map $http_upgrade $connection_upgrade`, nama yang sama akan membuat `nginx -t` gagal.

Aktifkan dengan aman:

```bash
sudo ln -s /etc/nginx/sites-available/hms.domainanda.com /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

`reload` (bukan `restart`) tidak memutus koneksi site lain. Jika `nginx -t` gagal, **jangan reload**. Perbaiki dulu atau hapus symlink-nya (lihat bagian Rollback).

---

## 8. SSL hanya untuk subdomain

```bash
sudo certbot --nginx -d hms.domainanda.com
```

Pilih opsi redirect HTTP ke HTTPS. Certbot hanya mengubah file `hms.domainanda.com`, dan sertifikat domain lain tidak tersentuh. Cek perpanjangan otomatis: `sudo certbot renew --dry-run`.

---

## 9. Trusted proxy

Karena request datang dari Nginx (127.0.0.1), Laravel harus mempercayai header `X-Forwarded-*` agar URL tetap `https://` dan IP tamu terbaca benar (juga untuk rate limiter). Aplikasi membaca ini dari `.env`:

```dotenv
TRUSTED_PROXIES=127.0.0.1
```

Lalu `php artisan optimize && sudo supervisorctl restart hms:hms-octane`. Jangan isi `*`, karena klien bisa memalsukan IP lewat `X-Forwarded-For`.

---

## 10. Scheduler (cron)

**Tambahkan** satu baris ke crontab `www-data` tanpa menimpa isi yang sudah ada:

```bash
( sudo crontab -u www-data -l 2>/dev/null; echo "* * * * * cd /var/www/hms-sync && php artisan schedule:run >> /dev/null 2>&1" ) | sudo crontab -u www-data -
sudo crontab -u www-data -l     # pastikan baris project lain masih ada
```

Jangan pakai `crontab -e` lalu menyalin isi file lain, dan jangan menjalankan `crontab file.txt`, karena keduanya mengganti seluruh crontab.

---

## 11. Uji

```bash
curl -I https://hms.domainanda.com                # 200
curl -I https://hms.domainanda.com/up             # health check Laravel
sudo supervisorctl status hms:*                   # semua RUNNING
redis-cli -n 5 --scan --pattern 'hms_sync_*' | head   # key HMS ada di DB 5
redis-cli -n 6 --scan --pattern 'hms_sync_*' | head   # cache di DB 6
```

Di browser:
- Login ke `/admin/login` dengan akun superadmin.
- Buka DevTools > Network > WS. Koneksi `wss://hms.domainanda.com/app/...` harus berstatus **101**.
- **Buka juga project lain** dan pastikan masih normal, termasuk login-nya (sesi tidak terlempar).

---

## 12. Deploy pembaruan

`/var/www/hms-sync/deploy.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
cd /var/www/hms-sync

php artisan down --retry=15 || true
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan migrate --force
php artisan optimize

php artisan octane:reload                 # reload worker Octane tanpa menghentikan proses
php artisan queue:restart                 # worker selesai job lalu mulai ulang dengan kode baru
sudo supervisorctl restart hms:hms-reverb

php artisan up
```

```bash
chmod +x deploy.sh && ./deploy.sh
```

Semua perintah hanya menyentuh program `hms:*`.

> Hindari `php artisan cache:clear` di server bersama kecuali `REDIS_CACHE_DB` sudah dipastikan khusus HMS. Perintah ini menjalankan `FLUSHDB` pada DB cache.

---

## 13. Rollback / melepas HMS tanpa jejak

```bash
# Matikan program HMS saja
sudo supervisorctl stop hms:*
sudo rm /etc/supervisor/conf.d/hms-sync.conf
sudo supervisorctl reread && sudo supervisorctl update

# Lepas site Nginx
sudo rm /etc/nginx/sites-enabled/hms.domainanda.com
sudo nginx -t && sudo systemctl reload nginx

# Hapus baris cron HMS saja
sudo crontab -u www-data -l | grep -v '/var/www/hms-sync' | sudo crontab -u www-data -

# (opsional) hapus data Redis HMS: hanya DB 5 & 6
redis-cli -n 5 FLUSHDB && redis-cli -n 6 FLUSHDB
```

---

## Daftar "JANGAN"

| Jangan | Alasan |
|---|---|
| Mengedit `sites-available/default` atau file site lain | bisa mematikan project lain |
| `systemctl restart nginx` tanpa `nginx -t` | satu typo mematikan semua site |
| `supervisorctl reload` / `restart all` | me-restart semua project |
| Memakai Redis DB `0` atau DB yang sudah terlihat di `INFO keyspace` | key dan cache saling tertimpa |
| `redis-cli FLUSHALL` | menghapus data Redis semua project |
| Mengubah `/etc/redis/redis.conf` atau `php.ini` global | berdampak ke semua project |
| Membuka port 8010/8091 di firewall | cukup localhost. Publik lewat Nginx 443 |
| `SESSION_DOMAIN=.domainanda.com` | cookie bentrok dengan project di domain utama |
| `crontab file.txt` | menimpa cron project lain |
| Menjalankan `DemoSeeder` di produksi | mengisi data palsu |

---

## Troubleshooting singkat

| Gejala | Cek |
|---|---|
| 502 Bad Gateway | `sudo supervisorctl status hms:hms-octane`, `tail storage/logs/octane.log`, port di Nginx sama dengan `--port` |
| Halaman `http://` / mixed content | `OCTANE_HTTPS=true`, `TRUSTED_PROXIES=127.0.0.1` (langkah 9), `APP_URL` https |
| WebSocket gagal | `hms-reverb` RUNNING, blok `location /app`, `VITE_REVERB_*` lalu `npm run build` ulang |
| Login project lain ikut keluar | `SESSION_COOKIE` dan `SESSION_DOMAIN` (langkah 5) |
| Perubahan kode tidak muncul | `php artisan optimize && php artisan octane:reload` |
| Job tidak jalan | `hms-worker` memakai `--queue=hms` yang sama dengan `REDIS_QUEUE` |
| Permission denied di storage | `sudo chown -R www-data:www-data storage bootstrap/cache` |
