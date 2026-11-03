# Testing standards

- Feature tests live under `tests/Feature`. They use the sqlite memory database from `phpunit.xml`.
- Assert the HTTP status, the JSON `code` when the endpoint is a status message, the `message` string the app already matches, and the wallet balance after a money call.
- Customer `approved` on a service request expects 422. Do not restore the old assertion that the row becomes `approved`.
- FTTH tests cover paying another account from the caller wallet, replaying a key against a different account (409 `MSG-FTTH-013`), an unknown bill that does not debit, and a 94-character key rejected by validation.
- Package cross-wallet reuse is tested by calling `BuyPackageService` directly. An HTTP test that switches users must not rely on the second bearer token while the session guard still holds the first user.
- Fake notifications in FTTH pay tests. A missing fake surfaces as a Firebase project error, not as a payment failure.
- Do not add a test that expects a foreign broadband account to be rejected unless the product rule changes.
- CI runs `php artisan test`. A money pull request should pass the related feature file before merge.
