# Flaky test summary – main branch (last 30 days)

**Window:** 2026-02-27 16:56 UTC through 2026-03-29 16:56 UTC  
**Scope:** GitHub Actions runs on `main`

## Test run statistics

- CI workflow runs: **78** total, **51 failures** (≈ **65.4%** failure rate).  
- All workflow runs on `main` (including non-test jobs such as docs/labeling): **986** total, **87** non-success outcomes (≈ **8.8%**).

## Flaky / intermittent failures observed

| Failing job(s) | Evidence (run id & date) | Symptom | Likely root cause |
| --- | --- | --- | --- |
| `Cypress (Gradeable)`, `Cypress (UI)` | [23699111375](https://github.com/Submitty/Submitty/actions/runs/23699111375) (2026-03-29), [23673407741](https://github.com/Submitty/Submitty/actions/runs/23673407741) (2026-03-28), [22926880531](https://github.com/Submitty/Submitty/actions/runs/22926880531) (2026-03-10) | PostgreSQL in the runner repeatedly logs `FATAL:  role "root" does not exist` and the Cypress containers tear down before tests start. | Infrastructure / database bootstrap race: the test DB is missing the expected `root` role when Cypress tries to connect. Marked **flaky** because the same jobs pass on other runs. |

## Non-flaky test-related failures (for awareness)

- `CSS Lint` failed in [23673407741](https://github.com/Submitty/Submitty/actions/runs/23673407741) due to vendor-prefixed `display: -ms-flexbox` and other lint violations in `simple_statistics.css`. This is a deterministic lint issue rather than flakiness.

## Notes and methodology

- Workflow runs were pulled with the GitHub Actions API for `main` within the 30-day window and filtered to CI runs for test statistics.  
- Failed job logs were reviewed for the most recent failures to identify repeating symptoms. Where the precise configuration fix was unclear, the root cause is recorded as infrastructure-related/unknown.
