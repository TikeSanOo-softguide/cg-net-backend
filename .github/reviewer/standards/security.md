# Security standards

- Authenticate mobile routes with Sanctum. Authenticate admin routes with the Fortify session guard.
- Customer mutations must check ownership on the server. A customer must not set `approved` on a service request.
- FTTH pay may target another broadband account. The debit stays on the authenticated user's wallet. One idempotency key stays bound to one account.
- Do not return billing-provider exception text to the client. A failed bill lookup is the generic `MSG-FTTH-004` response.
- Mock OTP codes stay behind `otp.mock.expose_code` and `local` or `testing`.
- CORS localhost patterns stay limited to `APP_ENV=local`.
- Reverb `allowed_origins` is currently `*`. Do not copy that into a new public endpoint. Do not tighten it without the real origin list.
- Rate limits that already exist (`serial-check` 5 per 30 minutes, `ftth-bill-pay` 10 per minute, top-up generation) stay on those routes.
- Do not log, cache, or return a plaintext top-up PIN except the existing admin generation download. The import cache currently holds plaintext pins for a day (TOPUP-002). Do not widen that.
- Do not commit `.env`, credentials, or tokens. `.env.example` `APP_DEBUG=true` is the local template, not production proof.
