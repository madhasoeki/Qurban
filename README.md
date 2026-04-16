# Qurban Bahagia

Aplikasi manajemen workflow qurban berbasis Laravel + Livewire untuk memantau dan mencatat proses operasional secara realtime.

## Gambaran Umum

Qurban Bahagia digunakan untuk:

- Mengelola data sohibul qurban dan data hewan.
- Memantau tahapan proses: penjagalan, pengulitan, cacah daging, cacah tulang, jeroan, dan packing.
- Melihat dashboard progress realtime.
- Mencatat jumlah distribusi dalam alur terpisah.
- Menyediakan akses publik (read-only dashboard/workflow monitor) jika dibutuhkan.

## Teknologi yang Digunakan

Backend:

- PHP 8.3+
- Laravel 13
- Livewire 4
- Flux UI (livewire/flux)
- Laravel Fortify (auth)
- Spatie Laravel Permission (role/permission)
- Laravel Reverb (WebSocket server)

Frontend:

- Vite 8
- Tailwind CSS 4
- Laravel Echo + Pusher JS protocol client (untuk koneksi ke Reverb)

Database:

- SQLite (default project ini)

## Struktur Proyek Singkat

- `app/Models`: model domain (`Hewan`, `Sohibul`, `Distribusi`, `User`).
- `resources/views/pages`: halaman Livewire (termasuk dashboard).
- `resources/js`: script frontend (durasi realtime, Echo listener).
- `database/migrations`: skema database.
- `tests/Feature`: test fitur utama.

## Setup Awal (Local Development)

### 1. Clone dan install dependency

```bash
composer install
npm install
```

### 2. Siapkan environment

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Siapkan database

```bash
php artisan migrate
```

### 4. Build asset

```bash
npm run build
```

### 5. Jalankan aplikasi (mode sederhana)

```bash
php artisan serve
```

## Menjalankan Development Harian

Untuk development lokal cepat (tanpa tunnel), jalankan:

```bash
composer run dev
```

Script ini menjalankan server app, queue listener, log tailing, dan Vite dev server secara paralel.

## Realtime Update (WebSocket)

Project ini sudah menggunakan event-driven update untuk dashboard (menggantikan polling per detik).

Agar realtime aktif, komponen berikut harus hidup:

- Laravel app
- Reverb server
- Queue worker/listener

Contoh command:

```bash
php artisan serve --host=127.0.0.1 --port=8000
php artisan reverb:start --host=0.0.0.0 --port=8080
php artisan queue:listen
```

## Menjalankan Online via Cloudflare Tunnel

Berikut adalah setup yang sudah dipakai pada proyek ini (dipertahankan dari catatan sebelumnya):

```text
Terminal 1: php artisan serve --host=127.0.0.1 --port=8000
Terminal 2: php artisan reverb:start --host=0.0.0.0 --port=8080
Terminal 3: cloudflared tunnel --protocol http2 --url http://127.0.0.1:8000
Terminal 4: cloudflared tunnel --protocol http2 --url http://127.0.0.1:8080
Terminal 5: php artisan queue:listen

Edit file .env
VITE_REVERB_HOST="servers-informal-executives-archive.trycloudflare.com" (url dari terminal 4)
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

Terminal 6: npm run build
```

Catatan penting:

- URL tunnel app (Terminal 3) dan URL tunnel websocket (Terminal 4) bisa berbeda.
- `VITE_REVERB_HOST` harus diisi URL tunnel websocket (Terminal 4), bukan URL app.
- Jika URL tunnel berubah, update `.env` lalu build ulang asset dengan `npm run build`.

## Testing dan Quality

Jalankan test:

```bash
php artisan test
```

Jalankan format/lint check:

```bash
composer run lint:check
```

## Troubleshooting Cepat

### Dashboard tidak update realtime

Periksa:

- `php artisan reverb:start` berjalan.
- `php artisan queue:listen` berjalan.
- `BROADCAST_CONNECTION=reverb` di `.env`.
- `VITE_REVERB_HOST/PORT/SCHEME` sesuai endpoint websocket publik (jika pakai tunnel).
- Setelah ubah `VITE_*`, jalankan `npm run build`.

### Muncul error Pusher cURL connect localhost:8080

Biasanya Reverb belum jalan atau host/port backend tidak sesuai. Pastikan server Reverb aktif dan konfigurasi `.env` benar.

## Lisensi

MIT
