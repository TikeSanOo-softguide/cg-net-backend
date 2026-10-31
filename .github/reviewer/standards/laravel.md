# Laravel standards

- Laravel 13, PHP 8.3. Do not raise the framework or PHP constraint in a feature pull request.
- New HTTP input goes through a form request. Controllers stay thin and call an existing service when one already owns that workflow.
- API status responses use `ApiMessage` or `ApiMessageException` and `App\Enums\MessageCode`. Add a new case instead of a one-off string code.
- Keep chat flow codes in JSON `code`. Put the MSG code in `message_code`.
- Do not change an existing `message` string that tests or the mobile app match.
- Admin routes that change data use the same `can:` permission pattern as the surrounding routes, plus `authorize()` on the form request.
- Public images use `StoresPublicImage` after `CmsRules::image()` or an equivalent `File::image()` rule. CSV imports stay on the `local` disk.
- Do not add a second implementation of ledger posting, PIN hashing, or FTTH pay.
- Tests for API behavior belong in `tests/Feature` and use the sqlite memory database already configured in `phpunit.xml`.
