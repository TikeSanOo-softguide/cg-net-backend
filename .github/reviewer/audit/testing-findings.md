# Testing findings

## TEST-001 — service-request test encoded self-approval — fixed

`tests/Feature/ServiceRequestApiTest.php` sent `status: approved` and expected that status. It now expects a validation error on `status`, then updates with `under_review`.

## TEST-002 — foreign FTTH account rejected — withdrawn

The original gap was the lack of a test that paying another account is rejected. The product rule is the opposite. Coverage now includes:

- `test_user_can_pay_another_broadband_account`
- `test_same_idempotency_key_cannot_pay_a_different_account`
- `test_unknown_bill_does_not_debit_the_wallet`
- `test_ftth_idempotency_key_leaves_room_for_a_refund_prefix`

Do not add a test that expects a foreign account to be rejected unless SEC-002's product rule changes.

## Package cross-wallet key

`PackagePurchaseTest::test_another_user_cannot_reuse_an_idempotency_key` calls `BuyPackageService` directly. An HTTP-only version in this suite can stay authenticated as the first user because Sanctum stateful domains include localhost. A new HTTP test that switches users must not assume the second bearer token wins while the session guard still has the first user.

## Full suite — 31 Oct 2026

`php artisan test` in this checkout: 523 passed, 1 risky, 3817 assertions, 65.88s. No failures.

The risky test is `Tests\Feature\MenuPageTest::test_authenticated_admins_see_empty_menu_pages_with_title_keys`. `MenuPages::all()` returns `[]`, so the loop never calls `assertOk()`. PHPUnit reports that the test performed no assertions. Guests are still redirected from `/customers`. This is not a failing money or authorization test.

## CI

`.github/workflows/ci.yml` runs `php artisan test` and `npm run build`. It does not run `composer audit`. That is DEVOPS-003, not a failed test gap by itself.

Wallet, top-up concurrency, package purchase, and FTTH payment tests exist and assert balances and status strings. A money change without one of those assertions is incomplete.
