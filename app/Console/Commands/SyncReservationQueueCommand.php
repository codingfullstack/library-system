<?php

namespace App\Console\Commands;

use App\Actions\Reservations\SyncReservationQueueAction;
use Illuminate\Console\Command;

class SyncReservationQueueCommand extends Command
{
    protected $signature = 'reservations:sync-queue
        {library_id : Library ID}
        {book_id : Book ID}
        {--delay-ms=0 : Optional delay before syncing, useful for operational race repros}';

    protected $description = 'Synchronize the reservation queue for one library/book pair.';

    public function handle(SyncReservationQueueAction $syncReservationQueue): int
    {
        $delayMs = max(0, (int) $this->option('delay-ms'));

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        $syncReservationQueue->handle((int) $this->argument('library_id'), (int) $this->argument('book_id'));

        $this->info('Reservation queue synchronized.');

        return self::SUCCESS;
    }
}
