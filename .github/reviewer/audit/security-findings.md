# Security findings

## SEC-001 — customer self-approval — fixed

Customer update rules for relocation, failure reports, Wi-Fi password changes, and installation applications accepted any `RequestStatus`, including `approved`. Controllers saved `validated()` after an owner check only.

Fix: those rules allow only `under_review` and `cancelled`. `UpdateChangePasswordRequest` still requires `status` because the app cancel action sends `cancelled` on PUT. Change-plan updates never accepted `status`.

Files: `app/Http/Requests/ServiceRequest/UpdateRelocationRequest.php`, `UpdateFailureReport.php`, `UpdateChangePasswordRequest.php`, `UpdateBroadbandApplicationRequest.php`.

Setting `approved` does not change the Wi-Fi password, package, or wallet. It only wrote the status column. That write is now rejected.

## SEC-002 — FTTH account ownership — changed, not closed

`PayFtthBillRequest` accepts any account number. `FtthBillPaymentService::pay` debits the authenticated user's wallet and extends that account. The payer does not need their own broadband binding.

Current safeguards in `FtthBillPaymentService`:

- A stored bill account that differs from the replayed key returns `MSG-FTTH-013` / 409.
- `lookupBillAmount` failure returns `MSG-FTTH-004` and does not debit.
- `throttle:ftth-bill-pay` is 10 per minute per user.
- The ledger note is `FTTH bill {account}`.

Do not add a check that forces the account to equal `users.broadband_account_number` unless the product rule changes again.

## SEC-003 — Reverb origins — open, accepted

`config/reverb.php` sets `allowed_origins` to `['*']`. Private channels in `routes/channels.php` still check the user or an admin permission. This is not an authentication bypass. Do not replace `*` until the real app and admin origins are known. A wrong allow-list breaks chat.

## SEC-004 — wallet adjust route middleware — open, accepted

`routes/web.php` `customers.wallet.adjust` has no `can:` middleware. `AdjustCustomerWalletRequest::authorize` requires `customers.update` or `billing.update`, and the adjustment posts through `LedgerPoster` with a new ULID key. Adding only `can:customers.update` would block billing staff who are allowed today.

## SEC-005 — nginx security headers — open, accepted

`docker/nginx/prod.conf` sets `server_tokens off` and denies dotfiles. It does not set `Content-Security-Policy`, `Strict-Transport-Security`, or `X-Frame-Options`. A strict CSP added without an admin UI check can break Inertia.

## Related controls that held

Mock OTP is returned only when `otp.mock.expose_code` is on and the environment is `local` or `testing` (`MockOtpProvider`). CORS origins come from `CORS_ALLOWED_ORIGINS`, with localhost patterns only when `APP_ENV` is `local`.
