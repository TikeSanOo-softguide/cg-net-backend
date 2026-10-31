# Database findings

## DB-001 — idempotency column versus refund prefix — fixed with WALLET-001

Same root cause as WALLET-001. The column stays `string(100)` unique. New FTTH keys are limited to 93 characters. Do not file a second change for the same column unless a caller still writes a refund key longer than 100 characters.

## Schema points reviewers should keep

- `wallets.balance` is `bigInteger`, default 0. Do not introduce float money for points.
- `ledger_transactions.transaction_no` and `idempotency_key` are unique.
- `ledger_entries` is unique on `(ledger_transaction_id, line_no)`.
- `top_up_card.serial_no` and `pin` are unique.
- `bill_payments.ledger_transaction_id` is unique. `broadband_account_number` is indexed and nullable.
- `users.broadband_account_number` is nullable and unique. Multiple unbound users may have null.

## Raw SQL

`selectRaw` and `orderByRaw` uses found in dashboard, reports, notifications, and chat are fixed SQL fragments, not request fields. They are not SQL injection by themselves. PostgreSQL accepts `DATE(created_at)` as used in the dashboard. Do not concatenate request sort fields into `orderByRaw`.

## Soft deletes

Customer service-request lists filter `deleted_at` null in addition to owner scope. A new unique index on a soft-deleted table must decide whether deleted rows still occupy the unique key. CMS slugs use `whereNull('deleted_at')` in `CmsRules::slug`.
