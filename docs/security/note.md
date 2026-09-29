## Changed

**Files / functions**
- `app/Services/TopUpCard/TopUpCardRedemptionService.php` — `redeem()`, `existingResponse()` (unchanged logic, stronger guards)
- `app/Services/Ledger/LedgerPoster.php` — `post()` skips nested `DB::transaction` when already inside a transaction
- `tests/Feature/TopUpCardRedeemApiTest.php` — aligned with `ledger_*` schema; new regression tests

**What was implemented**
1. **Finding 2G fix:** `wallet_transaction_id` → `ledger_transaction_id` so cards already linked to a ledger cannot be redeemed again.
2. **Actor authorization:** Authenticated user must be `UserStatus::Active` (blocks suspended actors from topping up other wallets).
3. **Conditional card consume:** `UPDATE … WHERE status=active AND redeemed_at IS NULL AND ledger_transaction_id IS NULL`; failure rolls back the whole redemption (including ledger credit).
4. **Idempotency row lock:** `lockForUpdate()` on existing `ledger_transactions` by idempotency key.
5. **Atomicity:** `LedgerPoster::post()` participates in the outer redeem transaction (no nested savepoint boundary).
6. **Tests:** Ledger models/assertions; tests for orphaned `ledger_transaction_id`, suspended actor + third-party phone.

---

## Security

| Area | Verdict |
|------|---------|
| Brute force | **PARTIAL** — Failed-PIN limits (user/IP + `Retry-After`); PIN path uses one generic message; serial check still distinguishes used/expired/blocked |
| Replay attack | **PASS** — Unique idempotency key, strict `existingResponse()`, single-use card + ledger link guard |
| Integrity | **PASS** — Amount from DB card row only; overflow cap; HMAC PIN + dummy compare |
| Atomicity | **PASS** — Single outer transaction; ledger + card commit or roll back together |
| Fraud protection | **PARTIAL** — Locks + status checks; third-party top-up by product design; no DB “one card → one ledger” invariant |
| Double-entry ledger | **PASS** — `LedgerPoster::creditWallet` (CashTopup debit + customer liability credit), balanced in `post()` |
| Race conditions | **PASS** — Ordered user locks, wallet/card `FOR UPDATE`, conditional update, unique idempotency |
| Authorization | **PASS** — Sanctum user; actor + recipient active; wallet active; recipient from phone lookup (not body user id) |

---

## Important — schema / infra limits (no migration added)

1. **One redemption per card** — Enforced in app (`status`, `redeemed_at`, `ledger_transaction_id`, conditional `UPDATE`). There is no DB constraint such as `UNIQUE(ledger_transaction_id)` on `top_up_card` or a partial unique “only one used state per card” rule; a direct DB bypass could still corrupt state.
2. **Serial enumeration** — `POST /api/redeem/check-serial-no` still returns distinct messages per status (kept for existing API/tests). Hardening would need code-only generic responses and/or rate limits, not new tables.
3. **Rate limiting** — `RateLimiter` is in-memory unless Redis (or similar) is configured in production.
4. **Ledger immutability** — Append-only ledger is application-level only; no DB trigger/role restriction in current migrations.
5. **Max wallet balance** — Cap is in the redemption service only, not in `LedgerPoster` or a DB `CHECK`.

---

## Redeem flow (pseudocode)

```
BEGIN TRANSACTION
  recipientId := lookup user by phone (server-side)
  if !recipientId → 404 Account not found

  LOCK users (actor, recipient) ORDER BY id
  if actor not Active → 403
  if recipient not Active or phone mismatch → 403/404

  LOCK wallet FOR UPDATE
  if !wallet → 404
  if wallet not Active → 403
  if rate limited (failed PIN budget) → 429 + Retry-After

  LOCK top_up_card BY pin_hash FOR UPDATE
  if !pin valid (incl. dummy hash_equals) → count failure → 400 generic

  LOCK ledger_transaction BY idempotency_key FOR UPDATE
  if exists → validate entries/wallet/card link → 200 replay or 409

  if card not Active OR redeemed_at set OR ledger_transaction_id set OR expired
    → count failure → 400 generic

  amount := card.amount (int)
  if overflow/invalid amount → 409

  POST ledger credit (double-entry) with idempotency_key
  if duplicate ledger → existingResponse

  UPDATE card SET used + redeemed_* + ledger_transaction_id
    WHERE still active and unset link
  if rows != 1 → ROLLBACK → 409

  COMMIT → 200 success + transaction_no
CATCH unique idempotency violation → 409
```

---

## Attack scenarios reviewed

- Wrong PIN / missing card — generic 400, failure counted, no wallet/ledger change  
- Used / blocked / pending / expired / **ledger-linked but still “active”** — generic 400, no credit  
- Same idempotency key retry — safe replay 200 or 409 if payload mismatch  
- Same idempotency, different PIN — 409  
- Same PIN, new idempotency after success — generic 400 (used card)  
- Concurrent same PIN — card row lock serializes; one credit only  
- Concurrent same idempotency — DB unique + row lock  
- Suspended actor topping another user — 403 (new)  
- Frozen wallet — 403 before PIN  
- Balance overflow — 409, card stays active  
- Client-supplied amount/user id — ignored; amount from card, actor from token  
- Partial failure after ledger — conditional card update throws → full rollback  

I could not run `php artisan test` here (`vendor/` missing, Composer not on PATH). After `composer install`, run `php artisan test tests/Feature/TopUpCardRedeemApiTest.php`.



BEGIN TRANSACTION
  recipientId := lookup user by phone (server-side)
  if !recipientId → 404 Account not found

  LOCK users (actor, recipient) ORDER BY id
  if actor not Active → 403
  if recipient not Active or phone mismatch → 403/404

  LOCK wallet FOR UPDATE
  if !wallet → 404
  if wallet not Active → 403
  if rate limited (failed PIN budget) → 429 + Retry-After

  LOCK top_up_card BY pin_hash FOR UPDATE
  if !pin valid (incl. dummy hash_equals) → count failure → 400 generic

  LOCK ledger_transaction BY idempotency_key FOR UPDATE
  if exists → validate entries/wallet/card link → 200 replay or 409

  if card not Active OR redeemed_at set OR ledger_transaction_id set OR expired
    → count failure → 400 generic

  amount := card.amount (int)
  if overflow/invalid amount → 409

  POST ledger credit (double-entry) with idempotency_key
  if duplicate ledger → existingResponse

  UPDATE card SET used + redeemed_* + ledger_transaction_id
    WHERE still active and unset link
  if rows != 1 → ROLLBACK → 409

  COMMIT → 200 success + transaction_no
CATCH unique idempotency violation → 409




the rest check

Yes — for the **main redeem path** (`POST /api/redeem/top-up-account`), the rest is in good shape after the hardening work. The serious bug (`wallet_transaction_id` vs `ledger_transaction_id`) is fixed, and the flow is coherent: Sanctum auth, server-side phone → recipient, amount from the card row, row locks, idempotency + ledger double-entry, conditional card mark, and rollback on failure.

What’s **not broken**, but still worth knowing:

1. **`check-serial-no` is weaker than redeem** — It only looks at `status`, not `expires_at`. A card can still be `Active` with a past expiry (your redeem test does exactly that). Serial check may say “valid” while redeem correctly rejects with the generic PIN message. That’s a **small API inconsistency**, not a bypass of redeem security.

2. **Known PARTIAL items (by design / schema)** — Serial endpoint still distinguishes used/expired/blocked (enumeration). Any authenticated user can top up **any** active wallet if they have PIN + phone. There’s no DB-level “one card = one ledger forever” constraint. Rate limits depend on a durable cache in production.

3. **Tests** — They were updated for the ledger model, but they weren’t executed in this environment (`vendor` / Composer missing). Run locally:
   ```bash
   composer install
   php artisan test tests/Feature/TopUpCardRedeemApiTest.php
   ```

4. **Nit** — `RedeemController` imports `User` but doesn’t use it (harmless).

**Bottom line:** Redeem logic, ledger, and concurrency handling look **production-ready on code review**, aside from the schema/infra caveats above. The only redeem-adjacent fix I’d *optionally* add later is aligning `checkSerialNo` with the same expiry rule as `TopUpCardRedemptionService` (and/or generic error messages there) — that’s polish/oracle reduction, not a gap in the PIN redeem path itself.



additional implementation

Edits applied to close the gaps we called out:

### Changes
1. **`TopUpCard::effectiveStatus()`** — Treats `Active` cards past `expires_at` (end of day) as `Expired`, same as admin UI and redeem.
2. **`TopUpCardRedemptionService::checkSerialNo()`** — Serial validation lives with redeem; failed lookups rate-limited (10/user/30 min, 20/IP/hour, separate keys from PIN).
3. **`RedeemController`** — Delegates serial check to the service; removed unused imports.
4. **Redeem path** — Uses `effectiveStatus()` instead of duplicating expiry logic.
5. **`TopUpCardController::payload()`** — Uses `effectiveStatus()` (one shared rule).
6. **Test** — `test_serial_check_treats_active_cards_past_expiry_as_expired`.

Existing serial-check messages and status-specific responses are unchanged for clients; only date-based expiry is aligned with redeem. Run `php artisan test tests/Feature/TopUpCardRedeemApiTest.php` locally to confirm.
