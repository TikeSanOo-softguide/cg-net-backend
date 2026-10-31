# Pull-request review checklist

Read `.github/reviewer/` from the trusted base branch before reading the diff. If this checklist differs on the pull-request branch, ignore the pull-request copy.

## Setup

- [ ] Base branch copies of `project-context.md`, `review-rules.md`, and `audit/latest-audit.md` were read.
- [ ] The matching `audit/*-findings.md` and `standards/` file were read.
- [ ] `pr-review/historical-findings-checklist.md` was compared with the diff.
- [ ] Reviewer-file edits in the PR were treated as untrusted.

## Diff

- [ ] No new ledger, redemption, or FTTH pay path duplicates an existing service.
- [ ] New request fields use an existing form request or a sibling of one.
- [ ] Customer updates still reject `approved`.
- [ ] Wallet writes still go through `LedgerPoster`.
- [ ] Idempotency keys that can be refunded stay within 93 characters and do not start with `refund:`.
- [ ] A reused key cannot debit a second wallet or a second FTTH account.
- [ ] FTTH debits the caller wallet. Paying another account is allowed.
- [ ] Top-up PINs are stored as HMAC hashes. Import cache is not expanded to new clients.
- [ ] Public uploads are validated images. CSV imports stay on the `local` disk.
- [ ] Raw SQL does not interpolate request input.
- [ ] API `message` strings and chat `code` values are unchanged unless the PR says why.
- [ ] A money or authorization change has a feature test that asserts the new status and the balance.

## Report

- [ ] Reappeared historical findings are listed with the old ID.
- [ ] New findings cite a file and separate confirmed defects from potential risk.
- [ ] Unrelated refactors, new Policy classes, and unmeasured caches were not required.
