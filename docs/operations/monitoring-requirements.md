# Monitoring requirements

Critical processes must be traceable without logging PII. Logs should use structured fields and technical IDs:

- `request_id`
- `job_id`
- `library_id`
- `book_id`
- `book_copy_id`
- `reservation_id`
- `loan_id`

Do not log names, emails, membership numbers, device tokens, notes, or free-text cancellation reasons.

## Signals

| Process | Required event | Required context | Notes |
| --- | --- | --- | --- |
| Reservation create | `reservation_create_failed` | `book_id`, DB error metadata when present | Used to spot duplicate-active-reservation and DB invariant failures. |
| Reservation cancel | `reservation_cancel_failed` | `library_id`, `book_id`, `reservation_id` | Tracks failed cancel mutations and stale client/capability cases. |
| Reservation queue sync | `reservation_queue_sync_failed` | `library_id`, `book_id`, deadlock fields | Queue mutex remains scoped by `library_id + book_id`. |
| Queue sync command | `reservation_queue_command_failed` | `library_id`, `book_id`, deadlock fields | CLI failures must be visible in scheduler/worker logs. |
| Reservation expiration | `reservation_expiration_failed` | deadlock fields when present | Command must remain idempotent and safe to rerun. |
| Tenant preflight | `tenant_integrity_preflight_failed` | `violation_count`, `violation_types` | Non-zero exit code remains the deploy gate. |
| Loan borrow | `loan_borrow_failed` | `library_id`, `book_id`, `book_copy_id`, DB error metadata | Used to diagnose lock/deadlock and active-loan invariant failures. |
| Loan return | `loan_return_failed` | `library_id`, `book_id`, `book_copy_id`, DB error metadata | Used to diagnose return/sync failures. |
| FCM send | `fcm_delivery_failed` | `token_hash`, exception class | Raw device tokens must not appear in logs or result payloads. |

## Runtime checklist

- Preserve or generate `X-Request-Id` on every HTTP request and include it in structured log context.
- Alert on any `*_failed` event from reservation, loan, scheduler, queue, tenant preflight, or FCM delivery.
- Alert separately when `is_deadlock=true`; include operation and affected IDs in the alert body.
- Keep Laravel failed jobs visible through the production queue driver and dashboard/log aggregation.
- Run `php artisan tenants:audit-integrity` before DB invariant deploys and retain its output in deploy logs.
- Run scheduler with overlap controls documented in `docs/deploy-scheduler-and-queue.md`.
- Confirm queue workers use bounded retries/backoff and that jobs dispatched from transactions use after-commit semantics.
