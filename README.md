# YNO Admin (CG-Net Backend)

Staff admin for a broadband / ISP operator. Operators manage customers, CPE, packages, billing, top-up cards, service requests, support chat, CMS, and settings from one web console.

| | |
| --- | --- |
| **UI brand** | Yaung Ni Oo / Smart Link |
| **App name** | YNO Admin (`APP_NAME`) |
| **Branch note** | Default DB is **PostgreSQL 16** (MySQL → PostgreSQL Phase A) |

---

## Stack

| Layer | Choice |
| --- | --- |
| Backend | PHP 8.3, Laravel 13, Fortify (admin session), Sanctum |
| Frontend | React 19, Inertia.js 3, TypeScript, Vite 8, Tailwind CSS 4 |
| UI | Radix, Lucide, Recharts |
| Data | **PostgreSQL 16**, Redis 7 (cache + queues) |
| Docker | PHP-FPM, Nginx 1.27, Postgres, Redis (MySQL optional profile) |
| Auth | `Admin` model (guard `web`). `User` = customer |
| i18n | `en` / `my` / `zh` — `lang/` |
| Also | Spatie Permission + Activity Log, Reverb, FCM, Excel, Sentry, Scramble |

---

## Requirements

- **Docker Desktop** (recommended), **or**
- PHP 8.3 (+ `pdo_pgsql`, `redis`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `bcmath`), Composer, Node **22+**, PostgreSQL 16, Redis 7

| Port | Service |
| --- | --- |
| `8080` | Docker Nginx (app) |
| `5432` | PostgreSQL |
| `6379` | Redis |
| `5173` | Vite HMR |
| `8000` | Local `artisan serve` (no Docker) |

---

## Step-by-step — Docker (recommended)

### 1. Clone and env

```bash
git clone <repo-url> cg-net-backend
cd cg-net-backend
cp .env.example .env
```

`.env.example` already uses:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cg_net
DB_USERNAME=cgnet
DB_PASSWORD=secret
```

Compose overrides `DB_HOST=pgsql` inside containers.

### 2. Start containers

```bash
docker compose up -d --build
```

Services: `app`, `nginx`, `vite`, `pgsql`, `redis`.

### 3. Install PHP deps + key

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
```

### 4. Migrate (and optional seed)

```bash
# Schema only (safe for prod-like)
docker compose exec app php artisan migrate --force

# Local demo data (do NOT use on production)
docker compose exec app php artisan migrate --seed --force
```

### 5. Frontend

```bash
npm ci
npm run build
```

Vite HMR is already running via the `vite` service when you use Compose.

### 6. Open the app

[http://localhost:8080](http://localhost:8080)

### 7. Stop

```bash
docker compose stop          # stop
docker compose down          # remove containers (volumes kept)
docker compose down -v       # also wipe DB/redis volumes
```

| Service | Container | Host port |
| --- | --- | --- |
| Nginx | `cg-net-nginx` | `8080` (`NGINX_PORT`) |
| PHP-FPM | `cg-net-app` | 9000 (internal) |
| Vite | `cg-net-vite` | `5173` |
| PostgreSQL 16 | `cg-net-pgsql` | `5432` (`FORWARD_DB_PORT`) |
| Redis 7 | `cg-net-redis` | `6379` |
| MySQL 8.4 (optional) | `cg-net-mysql` | profile `mysql` |
| Reverb (optional) | `cg-net-reverb` | profile `reverb` |

Database defaults: `cg_net` / `cgnet` / `secret`.

---

## Step-by-step — local (no Docker)

### 1. Install Postgres + Redis + PHP extensions

```bash
# Ubuntu example
sudo apt install -y php8.3-{pgsql,redis,mbstring,xml,curl,zip,gd,bcmath} \
  postgresql redis-server
```

### 2. Create database

```bash
sudo -u postgres psql -c "CREATE USER cgnet WITH PASSWORD 'secret';"
sudo -u postgres psql -c "CREATE DATABASE cg_net OWNER cgnet;"
```

### 3. App setup

```bash
cp .env.example .env
# Set DB_* and APP_URL=http://localhost:8000
composer install
php artisan key:generate
php artisan migrate --force
npm ci && npm run build
php artisan storage:link
```

### 4. Run

```bash
composer run dev
# or: php artisan serve  +  npm run dev
```

Open [http://localhost:8000](http://localhost:8000).

Shortcut: `composer setup` then `php artisan db:seed` (local only).

---

## Optional: MySQL (legacy)

Phase A defaults to PostgreSQL. MySQL remains available during cutover:

```bash
docker compose --profile mysql up -d mysql
```

Then in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1   # or mysql in Compose
DB_PORT=3306
```

App search uses `whereLike` → `ilike` on Postgres / `like` on MySQL.

---

## Seed logins (local / staging only)

Password for all seeded admins: `password`

| Role | Username |
| --- | --- |
| Super Admin | `Super Admin` |
| Staff Officer | `Staff Officer` |
| Support Agent | `Support Agent` |

**Do not** run `--seed` on production.

---

## Production (EC2 + DB) — short checklist

1. Clone **`production`** (or deploy branch) to `/var/www/cg-net-backend`
2. Copy `.env.production.example` → `.env`
3. Set `APP_URL`, `DB_*` (Postgres host), Redis, `APP_DEBUG=false`
4. `composer install --no-dev --optimize-autoloader`
5. `php artisan key:generate` (once)
6. `php artisan migrate --force`  ← **no seed**
7. `npm ci && npm run build`
8. `php artisan storage:link`
9. `php artisan config:cache && route:cache && view:cache`
10. Nginx → `public/`, Supervisor `queue:work redis`, cron `schedule:run`

Domain example: `https://yno-admin.chocobot.site`

---

## MySQL → PostgreSQL (Phase A) notes

Already in this codebase:

- `pdo_pgsql` in Docker image
- Compose service `pgsql` (Postgres 16)
- Portable unique handling (`UniqueConstraintViolationException`)
- Search macros `whereLike` / `orWhereLike`
- PG-compatible top-up status default migrations
- Renamed `wallet_entries` migration

**Not in Phase A (later):** Octane, PgBouncer, pgloader data copy, wallet `lockForUpdate` service.

Live MySQL data → Postgres: use **pgloader** (or dump/restore) in a planned cutover — see team migration guide.

---

## Project layout

```
app/
  Http/Controllers/{Domain}/
  Models/
  Support/Like.php          # MySQL like / PG ilike
database/                   # migrations, factories, seeders
docker/                     # nginx + php.ini
lang/{en,my,zh}.json
resources/js/pages/{Domain}/
routes/web.php
```

Frontend alias: `@` → `resources/js`.

---

## Useful commands

```bash
php artisan test
php artisan lang:check
php artisan route:list
docker compose exec app php artisan migrate --force

# Reverb later
# 1. VITE_REVERB_ENABLED=true
# 2. docker compose --profile reverb up -d reverb
# 3. docker compose restart vite

# Legacy MySQL container
docker compose --profile mysql up -d mysql
```

---

## Environment notes

| Variable | Purpose |
| --- | --- |
| `APP_NAME` / `APP_URL` | Title + base URL (`YNO Admin`) |
| `DB_CONNECTION` | `pgsql` (default) or `mysql` |
| `DB_HOST` / `DB_PORT` | `pgsql`/`5432` in Docker; RDS/EC2 host in prod |
| `CACHE_STORE` / `QUEUE_CONNECTION` / `REDIS_*` | Redis |
| `VITE_APP_NAME` | Browser tab title |
| `VITE_REVERB_ENABLED` | Echo → Reverb (off by default) |
| `FIREBASE_*` / `SENTRY_*` | Optional |
| `NGINX_PORT` | Docker HTTP (default 8080) |

Production template: `.env.production.example`.

---

## License

MIT (Laravel skeleton). Application code is for the Smart Link / YNO Admin project.
