# Audit history

## 2026-10-31 — initial audit

Read-only review of the Laravel backend. No confirmed double-spend. High findings: customer self-approval (SEC-001), FTTH pay not tied to the bound account (SEC-002, later changed by product decision), FTTH refund key longer than varchar(100) (WALLET-001), package replay calling a missing method (WALLET-002).

Modules not fully line-audited were recorded in `latest-audit.md`. Knowledge-base files were written only after explicit approval.

## 2026-10-31 — remediations

- SEC-001 and TEST-001 fixed. Customer `approved` is rejected.
- WALLET-001 fixed. FTTH idempotency keys are capped at 93 characters.
- WALLET-002 fixed. Cross-wallet package key reuse returns 409.
- SEC-002 was not closed. Paying another broadband account from the caller's wallet is the product rule. Safeguards added: one key per account, no debit when the bill cannot be looked up, 10/minute limiter, ledger note `FTTH bill {account}`.
- A mobile pay-bill screen and `POST /api/ftth-bills/quote` were started and then removed. Pay Bill in the app is the previous placeholder. `POST /api/ftth-bills/pay` remains.

## 2026-10-31 — remaining module read

CSV import hashes pins before insert and stores the file on the `local` disk. The import chunk cache holds plaintext pins for one day (TOPUP-002). Report `selectRaw` and `whereRaw` expressions reviewed are static SQL or bound dates. CMS images are jpeg, png, or webp, max 5120 KB, stored on the `public` disk after validation. Production deploy resets the `production` checkout and runs `php artisan migrate --force`. No application code was changed for these notes.

## 2026-10-31 — verification

`php artisan test`: 523 passed, 1 risky (`MenuPages::all()` is empty), no failures. Packagist was unreachable, so dependency advisories were read from OSV instead of `composer audit` (DEVOPS-004). Local Postgres `EXPLAIN ANALYZE` of the dashboard top-up aggregate: 7.499 ms on about 899 ledger rows. Production data was not queried. No application code or lockfile was changed.

## 2026-10-31 — accepted, not scheduled

TOPUP-001, TOPUP-002, SEC-003, SEC-004, SEC-005, WALLET-003, PERF-001, ARCH-001, ARCH-002, DEVOPS-001, DEVOPS-002, DEVOPS-003, DEVOPS-004. Reasons are in the category files. A later PR that makes one of these worse should still be flagged.
