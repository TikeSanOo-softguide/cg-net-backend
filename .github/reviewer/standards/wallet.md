# Wallet standards

- Balances are integer points. Use `bigInteger`. Do not store points as floats.
- Every credit and debit goes through `LedgerPoster`.
- A posting has two balanced lines, locks the wallet with `lockForUpdate`, and rejects a negative balance.
- `idempotency_key` is unique and at most 100 characters. Client keys that may later be refunded are at most 93 characters and must not start with `refund:`.
- Replaying a key for a different wallet, card, or FTTH account is a 409, not a second posting.
- Package cross-wallet reuse is `MSG-PKG-011`. FTTH account mismatch is `MSG-FTTH-013`.
- Top-up marks a card used only after the ledger row for that wallet and card was created in this attempt.
- Admin adjustments use a server-generated ULID key. They do not accept the client's idempotency key.
- A wallet change without a feature test that asserts both balances is incomplete.
