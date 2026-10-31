# Top-up findings

## TOPUP-001 — serial check discloses card state — open, accepted

`POST /api/redeem/check-serial-no` tells an authenticated caller whether a serial is valid, already used, or invalid. The route uses `throttle:serial-check` (5 attempts per 30 minutes in `AppServiceProvider`). The check does not redeem the card or credit a wallet.

Do not collapse the three results into one message unless the product decision changes. The mobile app shows different copy for used and invalid serials.

## Controls that must stay

- `TopUpCardPin::hash` is HMAC-SHA256. The `pin` column stores the digest. The model hides `pin`.
- `serial_no` and `pin` are unique (`2026_08_31_040000_create_top_up_card_table`).
- Redemption locks the wallet and the card, and checks `wasRecentlyCreated` before marking the card used.
- `existingResponse` returns 409 when the wallet, card, or ledger lines do not match the replay.
- CSV import stores hashed pins. Plain pins appear in the generation download payload for the admin who just generated them. That file is not the stored column.

## TOPUP-002 — import cache holds plaintext pins — open, accepted

`ImportGeneratedTopUpCardsJob::insertChunk` puts the chunk, including the plaintext `pin`, in cache for one day while the chunk status is `inserting`. The database insert uses `TopUpCardPin::hash`. The card row does not store the plaintext PIN. The cache entry does, until it expires or the chunk completes.

This is not a customer API. Do not change the import in an unrelated pull request. A change that returns that cache payload to the browser, or that inserts `$row['pin']` instead of the hash, is a defect.

## Import

`OfficeController` validates `extensions:csv` and `max:30000`, then stores the file on the `local` disk under `imports/top-up-cards`. The `extensions:csv` rule is duplicated in the same array. That is redundant, not an open upload path. Pins in the CSV are 16 digits. The job hashes them before insert and rejects a duplicate serial or pin hash.
