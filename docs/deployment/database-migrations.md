# Forward-only Database Migration Procedure

This project treats production schema changes that protect data or enforce new invariants as forward-only. A `down()` method is not a rollback plan when reverting could lose data, recreate an obsolete schema, or violate data already written by newer code.

## Forward-only Migrations

| Migration | Why forward-only | Preflight |
| --- | --- | --- |
| `2026_07_23_030000_restrict_assigned_book_copy_foreign_key` | Reverting `assigned_book_copy_id` delete behavior to `SET NULL` can erase reservation assignment history. | `php artisan reservations:audit-legacy-ready --json` |
| `2026_07_23_040000_add_ready_reservation_completeness_check` | Dropping the READY completeness invariant permits invalid READY reservations after new code depends on it. | Built-in migration query lists incomplete READY reservation IDs. |
| `2026_07_23_050000_migrate_damaged_book_copy_status_to_condition` | Moves semantic state from copy lifecycle status to condition state; reversing can overwrite legitimate post-migration lifecycle values. | Review damaged/lost/maintenance copy counts and sample status history rows. |
| `2026_07_23_060000_add_active_user_book_unique_index_to_reservations_table` | Dropping the active reservation uniqueness invariant allows duplicate active reservations that newer code rejects. | Built-in duplicate active reservation query. |
| `2026_07_24_000000_create_reservation_queues_table` | Queue mutex rows are operational state for concurrency. Dropping them during rollback can break in-flight reservation operations. | `php artisan reservations:sync-queue <library_id> <book_id>` smoke on a staging copy. |
| `2026_07_28_000000_enforce_tenant_ownership_invariants` | Composite tenant FKs and supporting indexes prevent cross-tenant data states. Removing them is not a safe rollback. | `php artisan tenants:audit-integrity` |

Older bootstrap migrations may have reversible `down()` methods for local rebuilds. Do not treat them as a production rollback mechanism.

## Preflight

Run from the exact release artifact before production migration:

```bash
php artisan optimize:clear
php artisan tenants:audit-integrity
php artisan reservations:audit-legacy-ready --json
php artisan reservations:expire
php artisan migrate --pretend
```

The preflight must be read-only except for explicitly idempotent commands such as expiration. Any tenant integrity or legacy READY violation blocks deploy until the data owner approves a forward data repair.

## Backup or Snapshot

Before `php artisan migrate --force`:

1. Take a managed database snapshot, or run a logical backup with routines, triggers, and events included.
2. Record database engine and version.
3. Record current application commit SHA, migration table state, and current queue depth.
4. Verify the backup can be restored into a temporary database.
5. Keep the application artifact for both the current release and the previous release.

Rollback for forward-only migrations is restore-based. Do not rely on `php artisan migrate:rollback` in production.

## Migration Sequence

1. Deploy code that is compatible with both old and expanded schema when possible.
2. Run read-only preflight commands.
3. Enable maintenance mode for migrations that add constraints, generated columns, or long `ALTER TABLE` operations:

```bash
php artisan down --render=errors::503
```

4. Stop or drain queue workers for migrations touching reservations, loans, notifications, or tenant FKs.
5. Run:

```bash
php artisan migrate --force
```

6. Restart queue workers.
7. Disable maintenance mode:

```bash
php artisan up
```

8. Run smoke tests.

## Large Table Safety

Before adding indexes, generated columns, CHECK constraints, or FKs on `reservations`, `loans`, `book_copies`, `library_memberships`, or `scan_logs`:

- Estimate row count and index build time on a production-sized copy.
- Check whether the database version supports online DDL for the exact operation.
- Prefer two-step expand/contract when a backfill is required.
- Add nullable columns first, deploy code that writes both old and new fields, backfill in batches, verify, then add NOT NULL or FK constraints.
- Never guess tenant values during backfill.

## Compatibility Rules

Old application with new schema:
- New nullable columns and additive indexes are acceptable.
- New constraints must not reject writes that the old app can still send unless maintenance mode prevents old writes during deploy.
- If old code can write invalid data, use maintenance mode or deploy compatibility code first.

New application with old schema:
- New code may be deployed before constraints only if it does not require new columns or generated indexes at runtime.
- Capability or concurrency code may rely on application checks first, then DB constraints after preflight and migration.
- If new code requires a new column, use an expand migration first.

## Smoke Tests

After migration:

```bash
php artisan tenants:audit-integrity
php artisan test --group=mysql --fail-on-skipped
php artisan reservations:expire
```

Manual smoke:
- Login as admin and member.
- Create a reservation for an unavailable book.
- Return a copy and verify one READY reservation assignment.
- Cancel a READY reservation and verify queue advances once.
- Borrow a READY assigned copy and verify exactly one active loan.
- Open Android book details, reservations, loans, and notifications.

## Application Rollback

If the migration succeeded but the new application fails:

1. Keep the new schema in place.
2. Roll back only the application artifact to the previous compatible release.
3. Keep queue workers on a compatible version.
4. Do not run migration rollback.
5. If previous code is not schema-compatible, keep maintenance mode on and restore the database snapshot.

## Database Restore

Use restore only when schema or data must return to the pre-migration point:

1. Enable maintenance mode.
2. Stop queue workers and scheduler.
3. Restore the verified snapshot to the production database or fail over to the restored instance.
4. Confirm migration table matches the restored application release.
5. Run `php artisan migrate:status`.
6. Run tenant integrity audit and core smoke tests.
7. Restart scheduler and queue workers.
8. Disable maintenance mode.

## Responsible Person Checklist

- [ ] Release artifact SHA recorded.
- [ ] Current database snapshot ID recorded.
- [ ] Backup restore tested on a temporary database.
- [ ] `tenants:audit-integrity` passed.
- [ ] Legacy READY audit reviewed.
- [ ] Long ALTER TABLE risk reviewed.
- [ ] Maintenance mode decision recorded.
- [ ] Queue workers drain/stop plan ready.
- [ ] Smoke test owner assigned.
- [ ] Restore owner assigned.

## Restore Verification

After restore:

- `php artisan migrate:status` matches the intended release.
- `php artisan tenants:audit-integrity` exits 0.
- Reservation queue sync works for a known library/book.
- No duplicate active loans exist.
- No duplicate active user/book reservations exist.
- Scheduler and queue workers are running.
