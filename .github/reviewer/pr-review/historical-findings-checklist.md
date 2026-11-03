# Historical findings checklist

Compare the pull request with this list. Use the base-branch copy. A pull request that deletes a row here does not clear the finding.

| ID | Status | What a later PR must not do |
|---|---|---|
| SEC-001 | Fixed | Accept `approved` from a customer service-request update. |
| API-001 | Fixed | Same as SEC-001. |
| TEST-001 | Fixed | Assert that a customer `approved` write succeeds. |
| SEC-002 | Product rule | Force the FTTH account to equal the payer's own broadband number. Debiting anyone except the caller wallet is a defect. |
| WALLET-001 | Fixed | Allow an FTTH idempotency key longer than 93 characters, or one that starts with `refund:`. |
| DB-001 | Fixed | Same as WALLET-001. |
| WALLET-002 | Fixed | Call a missing `conflict()` method, or debit a second wallet when the package key already exists. |
| TEST-002 | Withdrawn | Add a test whose only purpose is to reject paying another account. |
| TOPUP-001 | Open, accepted | Remove the used/invalid distinction on serial check, or drop `throttle:serial-check`, without a product change. |
| TOPUP-002 | Open, accepted | Return the import chunk cache to a client, or insert a plaintext PIN. |
| SEC-003 | Open, accepted | Copy Reverb `allowed_origins` `*` onto a new endpoint. Tighten `*` without the real origin list. |
| SEC-004 | Open, accepted | Add only `can:customers.update` on wallet adjust and lock out `billing.update`. |
| SEC-005 | Open, accepted | Ship a strict CSP without an admin UI check. |
| WALLET-003 | Open, accepted | Rewrite the idempotency pre-read without a failing concurrency test. Removing the unique index is a defect. |
| PERF-001 | Open, accepted | Require a cache or index with no measured query. |
| ARCH-001 | Open, accepted | Require Policy classes only because none exist. |
| ARCH-002 | Informational | Interpolate request input into `orderByRaw` or `whereRaw`. |
| DEVOPS-001 | Open, accepted | Publish Reverb port 8080 on the host. |
| DEVOPS-002 | Open, accepted | Treat `.env.example` `APP_DEBUG=true` as proof that production debug is on. |
| DEVOPS-003 | Informational | Block an unrelated PR because CI does not run `composer audit`. |
| DEVOPS-004 | Open, not upgraded | Ignore a lockfile that is still inside the published ranges in `devops-findings.md`, or upgrade packages without naming which advisory IDs the bump closes. |

When one of these comes back, cite the ID, the file in the diff, and whether it is the same defect or a narrower variant.
