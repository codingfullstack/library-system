<?php

namespace App\Console\Commands;

use App\Actions\Reservations\SyncReservationQueueAction;
use App\Support\Observability\OperationDiagnostics;
use Illuminate\Console\Command;
use Throwable;

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

        $libraryId = (int) $this->argument('library_id');
        $bookId = (int) $this->argument('book_id');

        try {
            $syncReservationQueue->handle($libraryId, $bookId);
        } catch (Throwable $exception) {
            app(OperationDiagnostics::class)->failure('reservation_queue_command_failed', $exception, [
                'operation' => 'reservation_queue_command',
                'library_id' => $libraryId,
                'book_id' => $bookId,
            ]);

            throw $exception;
        }

        $this->info('Reservation queue synchronized.');

        return self::SUCCESS;
    }
}
