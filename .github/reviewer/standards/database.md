# Database standards

- Production is PostgreSQL. Tests use sqlite memory. A migration must work for both, or the test database must be called out.
- Unique indexes that protect money stay in place: `ledger_transactions.idempotency_key`, `ledger_transactions.transaction_no`, `ledger_entries (ledger_transaction_id, line_no)`, `top_up_card.serial_no`, `top_up_card.pin`, `bill_payments.ledger_transaction_id`.
- Do not widen `idempotency_key` without a reason. New client keys stay within 93 characters so `refund:` still fits.
- Soft-deleted uniqueness must say whether deleted rows still occupy the key. CMS slugs ignore soft-deleted rows.
- `whereRaw`, `selectRaw`, and `orderByRaw` use fixed SQL. Bind request values with query-builder placeholders. Do not interpolate sort fields or search text into raw SQL.
- Report queries may be uncached (PERF-001). Do not add an index or a cache table without a measured query.
- Do not run `migrate:fresh` against a shared or production database.
