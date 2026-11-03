# Performance findings

## PERF-001 — dashboard and report aggregates — open, accepted

`DashboardController` and the billing, customer, and end-of-day report controllers run `selectRaw` sums and counts on each request.

On 31 Oct 2026 the local Postgres 16 database `cg_net` (container `cg-net-pgsql`) had a planner estimate of 899 `ledger_transactions` rows. `EXPLAIN ANALYZE` for the dashboard query — `type = topup`, `status = completed`, last 30 days, `DATE(created_at)` plus `SUM(amount)` — used `ledger_transactions_type_index`, then filtered status and the date window. Execution time was 7.499 ms. Shared buffers hit 24. 328 rows matched. Separate indexes exist on `type`, `status`, and `created_at`. There is no composite `(type, status, created_at)` index.

That measurement is the local database only. Do not add a cache or a new index from it. If a later change makes one of these queries accept an unbounded date range, ask for an `EXPLAIN` on the database that will serve the traffic.

## What was not found

No N+1 was confirmed as a production defect during the audit. Missing eager loading should be reported only when the diff loads a relation inside a loop over a query result.
