# Project Context For AI Agents

This is a Laravel + Livewire library management system.

## High-Risk Domains

- multi-library membership and active library context
- branch-scoped staff permissions
- book copy lifecycle versus operational availability
- loans and active/unreturned loan state
- reservation queues, ready assignment, pickup branch, and FIFO behavior
- exports and API responses that must preserve tenant boundaries
- database invariants enforced by foreign keys, unique indexes, and tenant ownership checks

## Tenant Model

Most operational records belong to a library. Some staff actions are further limited by branch. Members can belong to more than one library. The active library is an action context and must be validated against the actor membership before it affects reads or writes.

## Review Expectations

When changing reservation, loan, book, copy, membership, or API code, check:

- whether every query is scoped to the intended library set
- whether branch filters match the actor role
- whether API and web contracts intentionally differ
- whether queued and ready reservations are counted separately
- whether unavailable lifecycle statuses are excluded from availability
- whether tests cover cross-library and cross-branch negative cases

## Test Notes

The project uses Pest and Laravel tests. Prefer the smallest relevant test set first, then broaden validation when touching shared logic. Full suite runs may need a higher PHP CLI memory limit in local environments.
