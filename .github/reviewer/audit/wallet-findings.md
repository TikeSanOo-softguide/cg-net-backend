# Wallet findings

## WALLET-001 — FTTH refund key length — fixed

`ledger_transactions.idempotency_key` is a unique string of length 100. A refund key is `refund:` plus the original key. FTTH pay now rejects keys longer than 93 characters and keys that start with `refund:` (`PayFtthBillRequest`). Package and top-up requests already used that cap.

Existing rows whose keys are already longer than 93 characters are not repaired by the validation change. No migration widened the column.

## WALLET-002 — missing package `conflict()` — fixed

`BuyPackageService::replayFromKey` called `$this->conflict()` after that method was removed. A second user reusing a key fataled. It now returns `coded(409, MessageCode::PkgKeyUsed)`. The second wallet is not debited. The test calls the service directly because stateful Sanctum in the test suite can keep the first user on the next HTTP call.

## WALLET-003 — idempotency read outside the lock — open, accepted

`LedgerPoster::post` reads an existing idempotency key before `lockForUpdate` (`LedgerPoster.php` around the pre-transaction lookup). The unique index still rejects a second insert. Top-up catches `UniqueConstraintViolationException` and does not mark the card used unless the transaction was just created for that wallet and card. Do not rewrite this path without a failing concurrency test.

## FTTH payer rule

`pay()` loads `$user->wallet` and debits that wallet. `bill_payments.broadband_account_number` stores the account that was paid. Refunds credit the same payer wallet. The bill owner's wallet is not selected.

## Admin adjustments

`WalletAdjustmentService` posts credits and debits through `LedgerPoster` with a fresh ULID idempotency key. It does not accept a client-supplied key.
