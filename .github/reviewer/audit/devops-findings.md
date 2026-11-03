# DevOps findings

## DEVOPS-001 — Reverb listen address — open, accepted

`docker-compose.prod.yml` starts Reverb with `--host=0.0.0.0 --port=8080`. Nginx `prod.conf` proxies `/app` to that service. Together with SEC-003 (`allowed_origins` `*`), any origin that can reach the websocket endpoint may open a connection. Channel authorization still applies. Do not publish port 8080 on the host, and do not change origins without the real host list.

Production compose drops all capabilities, sets `no-new-privileges`, and health-checks php-fpm, Horizon, and Reverb. Those controls stay.

## DEVOPS-002 — example debug flag — open, accepted

`.env.example` sets `APP_DEBUG=true` and `APP_ENV=local`. That file is the local template. CI copies it before tests. A production server `.env` was not read. Do not treat the example flag as proof that production debug is on.

## DEVOPS-003 — CI has no dependency audit — informational

`.github/workflows/ci.yml` installs Composer dependencies and runs `php artisan test`. It does not run `composer audit`. A later workflow may add it. Do not block an unrelated PR for this absence.

On 31 Oct 2026 `composer audit` could not download Packagist advisories. WSL failed to connect to packagist.org:443. Windows timed out. The check below used OSV instead.

## DEVOPS-004 — locked packages are inside published advisory ranges — open, not upgraded

OSV querybatch on 31 Oct 2026 checked all 204 packages in `composer.lock`. Five locked versions fall inside a published affected range. `composer.lock` was not changed. Reachability inside this application was not proven.

| Package | Locked | Advisory | Fixed in | Summary |
|---|---|---|---|---|
| laravel/framework | v13.26.1 | GHSA-5wcm-3v2w-8362 | 13.27.0 | Relationship inclusion can trigger unauthorized data destruction |
| laravel/framework | v13.26.1 | GHSA-f9x3-7vq9-cph6 | 13.29.0 | TokenGuard authentication bypass through non-string API credentials |
| laravel/framework | v13.26.1 | GHSA-jh5r-qr3c-85q8 | 13.30.0 | XSS in the debug page. Matters when `APP_DEBUG` is on |
| league/commonmark | 2.10.0 | GHSA-3q6v-r5mr-hxv8, GHSA-97jj-33gv-5xf9 | 2.10.2 | Table-extension denial of service, and a raw-HTML bypass |
| league/flysystem | 3.35.2 | GHSA-cxf4-7mrp-vvpr | 3.35.3 | Malformed UTF-8 can bypass the control-character path check |
| phpoffice/phpspreadsheet | 5.9.0 | GHSA-2phh-27fw-q4gc, GHSA-3x94-m842-937c, GHSA-j2w7-387v-m2rq, GHSA-rxj4-h86g-25gc | 5.10.0 | SSRF via image redirect, XXE scanner bypasses, ODS row-repeat denial of service |
| symfony/http-foundation | v7.4.16 | GHSA-r925-mgvr-h9cc | 7.4.19 | ReDoS in `HeaderUtils::split()` via `Cache-Control` |

Do not treat this table as permission to run `composer update` inside an unrelated pull request. A later upgrade should name which of these IDs it closes.

## Headers

SEC-005 covers the missing browser headers in `docker/nginx/prod.conf`.
