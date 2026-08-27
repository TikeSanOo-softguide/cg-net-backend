# Smart Link — CG-Net Admin

Staff admin for a broadband / ISP operator. Operators manage customers, CPE, packages, billing, vouchers, service requests, support chat, and settings from one web console.

The UI brand is **Smart Link**. The Laravel app name is **CG-Net Admin**.

This repo is the admin backend and Inertia React shell. Domain models and seed data exist. **Dashboard and authentication are live.** Other sidebar screens are placeholder pages until their CRUD UIs are built.

## Stack

| Layer | Choice |
| --- | --- |
| Backend | PHP 8.3, Laravel 13, Fortify (admin session auth), Sanctum |
| Frontend | React 19, Inertia.js 3, TypeScript, Vite 8, Tailwind CSS 4 |
| UI | Radix, Lucide, Recharts |
| Data | MySQL 8.4, Redis 7 (cache + queues) |
| Docker | PHP-FPM app, Nginx 1.27, MySQL, Redis |
| Auth audience | `Admin` model (session guard `web`). `User` is the customer record. |
| i18n | English, Myanmar (`my`), Chinese (`zh`) — `lang/` |
| Also wired | Spatie Permission + Activity Log, Reverb, FCM, Excel, Sentry, Scramble |

## What works today

**Live**

- Staff login (logo-only login card), password reset, 2FA challenge pages
- Dashboard: stats, 30-day charts, recent service requests
- Collapsible sidebar, top bar, dark mode, theme panel, language switcher
- Locale switch: `POST /locale/{en\|my\|zh}`

**Data layer (models + migrations + seeders, no admin CRUD yet)**

Customers, broadband accounts, CPE devices, packages, wallets, invoices/payments, vouchers, installation / failure / relocation / change-plan requests, regions, notifications, chat, banners, settings, staff admins.

**Placeholder menu pages** (title only, via `MenuPageController`)

Customers, CPE, packages, billing, vouchers, service requests, regions, notifications, support, banners, staff/roles, activity logs, reports, settings.

Public API is a Sanctum stub (`GET /api/user`) only.

## Requirements

- Docker Desktop (recommended), **or** PHP 8.3, Composer, Node 20+, MySQL 8.4, Redis 7
- Ports: `8080` (Docker web), `3306` (MySQL), `6379` (Redis). Local artisan uses `8000`.

## Run with Docker

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
npm install
npm run build
```

Open [http://localhost:8080](http://localhost:8080).

Compose overrides `DB_HOST=mysql`, `REDIS_HOST=redis`, and `APP_URL=http://localhost:8080`. The `vite` service watches `resources/` and hot-reloads the browser when you edit JS, CSS, or PHP views.

```bash
docker compose stop    # stop
docker compose down    # stop and remove containers (volumes kept)
```

| Service | Container | Host port |
| --- | --- | --- |
| Nginx | `cg-net-nginx` | `8080` (`NGINX_PORT`) |
| PHP-FPM | `cg-net-app` | 9000 (internal) |
| Vite (HMR) | `cg-net-vite` | `5173` (`VITE_PORT`) |
| Reverb (WebSocket) | `cg-net-reverb` | `8081` (`FORWARD_REVERB_PORT`) |
| MySQL 8.4 | `cg-net-mysql` | `3306` |
| Redis 7 | `cg-net-redis` | `6379` |

Database defaults: `cg_net` / `cgnet` / `secret`.

## Run locally (no Docker)

Point `.env` at local MySQL/Redis (`DB_HOST=127.0.0.1`, `APP_URL=http://localhost:8000`), then:

```bash
composer setup          # install, .env, key, migrate, npm install, build
php artisan db:seed
composer run dev        # artisan server + Vite
```

Or split: `php artisan serve` and `npm run dev`.

## Seed logins

Password for all seeded admins: `password`

| Role | Username |
| --- | --- |
| Super Admin | `Super Admin` |
| Staff Officer | `Staff Officer` |
| Support Agent | `Support Agent` |

Seed also creates Myanmar region trees, packages, sample customers (broadband + CPE + wallet), service requests, paid invoices, notifications, banners, and `support_hotline`.

## Project layout

```
app/
  Http/Controllers/{Domain}/{Domain}Controller.php
  Http/Requests/{Domain}/
  Models/               ISP domain
  Support/MenuPages.php Sidebar routes that are still placeholders
database/               migrations, factories, seeders
docker/                 nginx + php.ini
lang/{en,my,zh}.json    Nested JSON translations
resources/
  js/pages/{Domain}/    Customer/Index, Auth/Login, Dashboard/Index
  css/app.css
routes/web.php
```

Frontend alias: `@` → `resources/js`.

## Conventions

- **Auth:** Fortify against `admins`. Customers never log into this app.
- **Folders:** Group HTTP and Inertia files by domain (`Customer/CustomerController`, `Customer/Index`). Page names are PascalCase (`Auth/ForgotPassword`).
- **i18n:** Nested JSON keys in `lang/`. Keep locales in sync with `php artisan lang:check`.
- **Theme:** `ThemeProvider` (`isp-admin-theme`). Default brand teal `#173236`.
- **Sidebar:** Width 260px expanded / 88px collapsed; pin state in `isp-admin-sidebar-pinned`.
- **New menu screens:** add `{Domain}/{Domain}Controller` plus `pages/{Domain}/Index`, then remove that path from `MenuPages`.

## Useful commands

```bash
php artisan test
php artisan lang:check
php artisan route:list
docker compose exec app php artisan migrate --seed

# Turn Reverb on later
# 1. Set VITE_REVERB_ENABLED=true in .env
# 2. docker compose --profile reverb up -d reverb
# 3. docker compose restart vite
```

## Environment notes

Copy values from `.env.example`. Important ones:

| Variable | Purpose |
| --- | --- |
| `APP_NAME` / `APP_URL` | Title and base URL |
| `AUTH_GUARD` / `AUTH_PASSWORD_BROKER` | `web` / `admins` |
| `DB_*` | MySQL |
| `CACHE_STORE` / `QUEUE_CONNECTION` / `REDIS_*` | Redis |
| `VITE_REVERB_ENABLED` | Laravel Echo → Reverb. Off until you start the `reverb` profile. |
| `FIREBASE_*` / `SENTRY_*` | Push + error tracking (optional) |
| `NGINX_PORT` | Docker HTTP port (default 8080) |

## License

MIT (Laravel skeleton). Application code is for the Smart Link / CG-Net project.
