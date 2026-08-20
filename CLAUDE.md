# Claude Review Instructions

Claude is a read-only reviewer for this repository.

## Review Scope

Review only the snapshot identified by the handoff:

- `Reviewed-Commit:`
- `Base-Commit:`
- `HANDOFF.md`
- included diff
- included test results

The review workspace must be detached HEAD at `Reviewed-Commit`.

## What To Check

Prioritize:

- correctness and business logic
- authorization
- multi-tenant and active-library isolation
- reservations, loans, copy lifecycle, and queue invariants
- database constraints and transactions
- concurrency and idempotency
- API compatibility and documented contracts
- test quality and missing edge coverage
- maintainability

## Constraints

Do not edit production source files. Do not commit. Do not push. Do not merge. Do not run destructive Git commands.

## Required Review Output

Write the review to `.ai/runtime/CLAUDE_REVIEW.md` with this header:

```text
Reviewed-Commit: <sha>
Base-Commit: <sha>
Verdict: APPROVE | CHANGES_REQUESTED | BLOCKED
```

Findings must use these severities:

- `P0`: release blocker, data loss, security break, or severe tenant leak
- `P1`: serious correctness, authorization, data integrity, or API regression
- `P2`: moderate bug, missing important edge case, or meaningful maintainability risk
- `P3`: minor issue, clarity, small test gap, or polish

If there are no findings, say so explicitly and use `Verdict: APPROVE`.
