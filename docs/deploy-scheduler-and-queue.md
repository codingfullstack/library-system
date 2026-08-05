# Scheduler and Queue Production Runbook

This application relies on Laravel Scheduler and queued notifications for reservation lifecycle side effects.

## Scheduler

Run one scheduler entry on every application host:

```bash
* * * * * cd /path/to/library-system && php artisan schedule:run >> /dev/null 2>&1
```

The `reservations:expire` task is registered every minute with both `withoutOverlapping()` and `onOneServer()`. Production cache must therefore use a shared backend across app hosts, such as Redis, Memcached, database, DynamoDB, or another Laravel-supported atomic cache driver. Do not use `array` or per-host file cache for multi-host scheduler coordination.

The expiration command is idempotent. It locks reservation queue context by `library_id + book_id`, rechecks each READY reservation inside the transaction, expires only still-eligible rows, and then syncs the affected queue.

## Queue Workers

Use a persistent worker manager such as Supervisor or systemd. Recommended baseline:

```bash
php artisan queue:work database --queue=default --sleep=1 --tries=3 --backoff=30 --timeout=90
```

Queued library notifications also define bounded retry/backoff in code: 3 tries with 30, 120, and 300 second delays. The database queue `retry_after` should stay greater than the worker `--timeout`.

## Side Effects

Reservation and loan mutations must dispatch notifications only after transaction commit. Domain state changes stay inside database transactions; notification creation, broadcast, and FCM delivery run after the committed state is visible.

Repeated reservation sync, expiration retry, or notification retry must not create duplicate reservation notifications for the same `type + related reservation`.

## Failed Jobs

Keep `QUEUE_FAILED_DRIVER=database-uuids` enabled and run the failed jobs migration in production. Monitor:

```bash
php artisan queue:failed
```

Alert on any failed job count above zero. Failed jobs should be retried only after checking whether the related domain mutation committed successfully:

```bash
php artisan queue:retry <job-id>
```

Do not disable reservation concurrency tests or scheduler idempotency tests because of timing flakes; investigate lock ordering or infrastructure first.
