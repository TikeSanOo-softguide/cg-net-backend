# Project context

CG-NET is a production-oriented ISP backend. The mobile app talks to the Sanctum JSON API. Staff use an Inertia admin guarded by Fortify session auth and Spatie permissions.

Detected versions, from `composer.json` and `composer.lock` on 31 Oct 2026:

- PHP constraint `^8.3`
- Laravel framework `v13.26.1`
- Sanctum, Fortify, Horizon, Reverb, Spatie permission, activity log, Firebase, Excel, Sentry

Money is stored as integer points on `wallets.balance` (`bigInteger`). Ledger writes go through `App\Services\Ledger\LedgerPoster`. A posting must balance, locks the wallet with `lockForUpdate`, and rejects a negative balance.

High-value flows:

- Top-up redemption: `TopUpCardRedemptionService`. PIN is HMAC-SHA256. `pin` and `serial_no` are unique. The card and wallet rows are locked. A replay that does not match the same wallet and card returns 409 and does not mark the card used.
- Package purchase: `BuyPackageService`. Idempotency keys are unique. A key used by another wallet returns `MSG-PKG-011` / 409.
- FTTH bill pay: `FtthBillPaymentService`. The signed-in user's wallet is debited. The submitted `broadband_account_number` is the account whose plan is extended, including another subscriber's account. That is the current product rule. Refunds return to the payer. Idempotency keys are capped at 93 characters so `refund:` plus the key fits `idempotency_key` varchar(100).
- Service requests: customer updates may set `under_review` or `cancelled`. `approved` is rejected. Package-change updates do not accept `status`.

Reusable pieces reviewers should look for before accepting new code:

- `LedgerPoster` for any balance change
- `ApiMessage` and `App\Enums\MessageCode` for API status payloads
- `ApiFrameworkErrorResponse` for framework 401/403/404/422/429/500 on `api/*`
- Form requests under `app/Http/Requests`
- `StoresPublicImage` and `CmsRules::image()` for public images
- `TopUpCardPin` for PIN hashing

There are no Policy classes. Authorization is route `can:` middleware plus `FormRequest::authorize()`.

Tests use SQLite in memory (`phpunit.xml`). CI (`.github/workflows/ci.yml`) runs `php artisan test` on PHP 8.3 and `npm run build` on Node 22. CI does not run `composer audit`.
