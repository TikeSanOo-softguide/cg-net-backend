# API findings

## API-001 — customer status field — fixed with SEC-001

Same root cause as SEC-001. Do not add `status` back to a customer update request with `Rule::enum(RequestStatus::class)`.

## FTTH pay contract

`POST /api/ftth-bills/pay` body:

- `broadband_account_number` required, max 32
- `idempotency_key` required, length 8 to 93, must not match `/^refund:/i`

The account may belong to another subscriber. The wallet charged is the caller's. Success, processing, refund, and replay responses use `MessageCode` FTTH cases. `POST /api/ftth-bills/quote` does not exist. It was added and removed.

`GET /api/ftth-bills/pending-slip` and `paid-slips` still use the caller's own `broadband_account_number` only.

## Message contract

Status payloads include `code`, `level`, and `message`. Keep existing `message` strings. Chat flow codes `stale_chat_option` and `invalid_start_step_configuration` stay in `code`, with the MSG value in `message_code`.

Framework errors on `api/*` are decorated by `ApiFrameworkErrorResponse` (401, 403, 404, validation 422, 429, 500). Serial-check 429 stays `MSG-TOPUP-015`.

## Package buy

Idempotency is the `Idempotency-Key` header, max 93, not `refund:`. Cross-wallet reuse is 409 `MSG-PKG-011`.
