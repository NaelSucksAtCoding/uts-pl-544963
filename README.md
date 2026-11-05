# Booking Lapangan Futsal

[![CI](https://github.com/NaelSucksAtCoding/uts-pl-544963/actions/workflows/ci.yml/badge.svg)](https://github.com/NaelSucksAtCoding/uts-pl-544963/actions/workflows/ci.yml)

UTS Konstruksi & Evolusi Perangkat Lunak — Gunael Alexander Silalahi (544963)

Sistem manajemen booking lapangan futsal / mini soccer, terdiri dari RESTful API Laravel dan frontend Vue.js.

## Struktur Repository

| Folder | Isi |
|---|---|
| [`backend/`](backend) | RESTful API Laravel + Sanctum, feature test, dan [Postman collection](backend/postman/Booking_Lapangan_Futsal.postman_collection.json) |
| [`frontend/`](frontend) | Aplikasi Vue.js (Vite + Axios) yang memakai API backend |
| [`.github/workflows/ci.yml`](.github/workflows/ci.yml) | GitHub Actions: test backend, build frontend, dan deploy ke ghcr.io |
| [`docker-compose.yml`](docker-compose.yml) | Menjalankan seluruh sistem (MySQL, backend, frontend) dengan satu perintah |

## Menjalankan dengan Docker Compose

```bash
cp .env.example .env     # lalu isi APP_KEY, DB_PASSWORD, dan DB_ROOT_PASSWORD
docker compose up -d --build
```

Buka http://localhost:8080 dan login dengan `admin@futsal.test` / `password`. API juga bisa diakses langsung di http://localhost:8000/api (misalnya dari Postman).

| Service | Image | Keterangan |
|---|---|---|
| `db` | `mysql:8.4` | Data disimpan di named volume `db_data` |
| `backend` | `backend/Dockerfile` | Laravel dilayani FrankenPHP. Terhubung ke database lewat nama service `db` (`DB_HOST=db`); migrasi dan seeder dijalankan otomatis saat start |
| `frontend` | `frontend/Dockerfile` | Nginx menyajikan Vue dan meneruskan request `/api` ke service `backend` |

Alur data: browser → `frontend` (Nginx, `/api`) → `backend` (Laravel) → `db` (MySQL). Semua koneksi antar-container memakai nama service di jaringan Docker Compose, bukan alamat IP laptop.

Kredensial dibaca dari berkas `.env` di akar repository, yang tidak ikut di-commit. Contohnya ada di [`.env.example`](.env.example). `APP_KEY` dapat dibuat dengan:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32));"
```

Menghentikan sistem tanpa menghapus data: `docker compose down`. Data tetap ada setelah `docker compose up -d` dijalankan lagi. Untuk menghapus data sekaligus: `docker compose down -v`.

### FrankenPHP

Backend dijalankan dengan [FrankenPHP](https://frankenphp.dev) (`dunglas/frankenphp:1.9.1-php8.3.26-bookworm`), server aplikasi PHP yang dibangun di atas web server Caddy, menggantikan `php artisan serve` maupun PHP-FPM dengan Nginx. Konfigurasinya ada di [`backend/docker/Caddyfile`](backend/docker/Caddyfile), dengan access log aktif sehingga setiap request tercatat di log container.

Bukti request dilayani FrankenPHP:

```bash
docker compose logs backend | grep "FrankenPHP started"   # FrankenPHP aktif beserta versi PHP
docker compose logs backend | grep "handled request"      # access log setiap request
curl -I http://localhost:8000/api/bookings                # header "Server: Caddy" dan "X-Powered-By: PHP/8.3.26"
```

### Log dan Health Check

- Log Laravel keluar ke stdout dalam bentuk JSON, tidak ada berkas log di dalam container. Lihat dengan `docker compose logs backend`.
- Setiap log memuat `siapa`, `aksi`, dan `sebab`. Password, token, email, nama penyewa, dan alamat IP tidak dicatat.
- `GET http://localhost:8000/health` memeriksa database dan cache. Balasannya 200 kalau sehat, dan 503 kalau ada yang bermasalah, dengan nama bagian yang bermasalah di `bermasalah`.
- Healthcheck backend di `docker-compose.yml` memakai `/health`, jadi statusnya menjadi `unhealthy` saat database mati dan kembali `healthy` setelah database menyala.
- Log tiap container dibatasi 3 berkas @ 10 MB.

```bash
docker compose stop db     # setelah sekitar 30 detik backend menjadi unhealthy
docker compose ps
docker compose logs backend --tail 5
docker compose start db    # backend kembali healthy
```

## Menjalankan tanpa Docker

**Backend** (terminal 1):

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

**Frontend** (terminal 2):

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

Buka http://localhost:5173 dan login dengan `admin@futsal.test` / `password`.

Dokumentasi lengkap: [backend/README.md](backend/README.md) dan [frontend/README.md](frontend/README.md).

## Dockerfile

| Berkas | Isi |
|---|---|
| [`backend/Dockerfile`](backend/Dockerfile) | FrankenPHP 1.9.1 (PHP 8.3.26). `composer.json` dan `composer.lock` disalin dan dipasang sebelum kode aplikasi supaya cache lapisan bekerja |
| [`frontend/Dockerfile`](frontend/Dockerfile) | Multi-stage: Node.js membangun aplikasi, Nginx menyajikan hasil build (tanpa `node_modules`) |
| `backend/.dockerignore`, `frontend/.dockerignore` | Mengecualikan dependency, `.env`, database lokal, dan berkas yang tidak dibutuhkan |

Nilai rahasia seperti `APP_KEY` dan `DB_PASSWORD` tidak ditulis di Dockerfile, melainkan diberikan lewat environment variable saat container dijalankan (dari `.env` pada Docker Compose, atau dari GitHub Secrets pada job deploy).

## Alur Kerja Git

- `main`: kode stabil. `dev`: gabungan fitur yang sedang dikembangkan. `feat/*`: satu branch per fitur.
- Perubahan masuk ke `dev` dan `main` hanya melalui Pull Request (`feat/*` → `dev` → `main`).
- Branch protection pada `main` dan `dev` mewajibkan Pull Request dan status check CI lulus sebelum merge.

## Continuous Integration

Workflow [`ci.yml`](.github/workflows/ci.yml) berjalan pada setiap push ke `main`, `dev`, dan `feat/**`, serta pull request ke `main` dan `dev`:

| Job | Langkah | Berjalan pada |
|---|---|---|
| Backend (Laravel API) | `composer install` → `php artisan test` | Semua push dan pull request |
| Frontend (Vue.js) | `npm ci` → `npm run build` | Semua push dan pull request |
| Deploy (ghcr.io) | Build image backend dan frontend → uji image (backend dengan MySQL) → unggah ke ghcr.io | **Hanya push ke `main`**, setelah kedua job di atas lulus |

## Continuous Deployment

Job deploy mengunggah dua image ke GitHub Container Registry dengan tag nomor commit (`github.sha`), bukan `latest`:

- `ghcr.io/naelsucksatcoding/uts-pl-544963-backend:<commit-sha>`
- `ghcr.io/naelsucksatcoding/uts-pl-544963-frontend:<commit-sha>`

Nilai rahasia disimpan di **GitHub Secrets** (Settings → Secrets and variables → Actions), tidak di repository:

| Secret | Dipakai untuk |
|---|---|
| `APP_KEY` | Kunci enkripsi Laravel saat menguji image backend |
| `DB_PASSWORD` | Password MySQL saat menguji image backend |
