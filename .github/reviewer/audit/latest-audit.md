# Latest audit

Date: 31 Oct 2026. Read-only audit, then four approved remediations. Application behavior after those remediations is the baseline below.

Stack: PHP ^8.3, Laravel 13.26.1, PostgreSQL-oriented ledger, Sanctum API, Fortify admin, Horizon, Reverb.

No confirmed double-spend was found. Top-up redemption locks the card and wallet, stores an HMAC PIN, and unique indexes cover `pin`, `serial_no`, and `idempotency_key`.

## Counts after remediation

| Category | Critical | High open | Medium open | Low open |
|---|---:|---:|---:|---:|
| Security | 0 | 0 | 1 | 2 |
| Wallet | 0 | 0 | 0 | 1 |
| Top-up | 0 | 0 | 1 | 1 |
| Database | 0 | 0 | 0 | 0 |
| Performance | 0 | 0 | 1 | 0 |
| Architecture | 0 | 0 | 1 | 1 |
| API | 0 | 0 | 0 | 0 |
| Testing | 0 | 0 | 0 | 0 |
| DevOps | 0 | 0 | 2 | 2 |

Open medium items are accepted for now: TOPUP-001, SEC-003, PERF-001, ARCH-001, DEVOPS-001, DEVOPS-004. TOPUP-002 (plaintext pins in the import cache) is an open low item from the import read. None of these are scheduled code changes. DEVOPS-004 records published advisories against the locked packages. The lockfile was not upgraded.

## Fixed in this cycle

| ID | Result |
|---|---|
| SEC-001 / API-001 | Customer update requests accept only `under_review` and `cancelled`. `approved` is a validation error. |
| TEST-001 | `ServiceRequestApiTest` expects 422 for customer `approved`. |
| WALLET-001 / DB-001 | FTTH `idempotency_key` max is 93 and must not start with `refund:`. |
| WALLET-002 | `BuyPackageService::replayFromKey` returns `MSG-PKG-011` / 409 instead of calling a removed method. |
| SEC-002 | Product rule is pay-for-others. The payer wallet is debited. The submitted account receives the plan extension. A reused key for a different account returns `MSG-FTTH-013`. A bill lookup failure does not debit. Pay is limited to 10 requests per minute per user. |

## Still open

See the category files. Do not re-open a fixed ID unless the base-branch code shows the defect again.

## Inspection limits

Inspected: routes, ledger, top-up redemption, package buy, FTTH pay, wallet adjustment, service-request updates, OTP mock, CORS, Reverb, broadcast channels, production nginx and compose, CI and production deploy workflows, public image storage, CMS image rules, top-up CSV import including pin hashing and the import cache, and the feature tests named in the category files.

Sampled: dashboard and report `selectRaw` / `whereRaw` (literals or bound dates, not request SQL), Excel exports (`FromQuery` over an existing builder), and CMS image rules. Not line-audited: every CMS content body and every export column.

Follow-up on 31 Oct 2026:

- `php artisan test`: 523 passed, 1 risky, 3817 assertions, 65.88s. The risky test is `MenuPageTest` because `MenuPages::all()` returns an empty list, so the loop makes no assertions. No failures.
- `composer audit` could not reach packagist.org from WSL or Windows. The same lockfile was checked with the OSV querybatch API: 204 packages, 5 with published advisories (DEVOPS-004). `composer.lock` was not changed.
- Local Postgres 16 in container `cg-net-pgsql`, database `cg_net`. `ledger_transactions` planner estimate is 899 rows. `EXPLAIN ANALYZE` of the dashboard top-up aggregate used `ledger_transactions_type_index` and finished in 7.499 ms (328 rows in the 30-day window). This is the local database, not production. PHP CLI has no `pdo_pgsql`; the plan was read with `psql` inside the container.
