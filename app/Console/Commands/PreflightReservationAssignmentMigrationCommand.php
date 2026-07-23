<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreflightReservationAssignmentMigrationCommand extends Command
{
    protected $signature = 'reservations:preflight-assignment-migration {--json : Emit machine-readable JSON}';

    protected $description = 'Read-only preflight checks for reservation assignment migration safety.';

    public function handle(): int
    {
        $checks = [
            'database_version' => $this->databaseVersion(),
            'pending_migrations' => $this->pendingMigrations(),
            'incomplete_ready' => $this->incompleteReady(),
            'duplicate_assigned_copy' => $this->duplicateAssignedCopy(),
            'active_loan_conflict' => $this->activeLoanConflict(),
            'assigned_copy_book_mismatch' => $this->assignedCopyMismatch('book_id'),
            'assigned_copy_library_mismatch' => $this->assignedCopyMismatch('library_id'),
            'assigned_copy_branch_mismatch' => $this->assignedCopyBranchMismatch(),
            'expired_ready' => $this->expiredReady(),
            'reserved_at_suspicious' => $this->reservedAtSuspicious(),
        ];

        $status = collect($checks)->contains(fn (array $check) => $check['level'] === 'BLOCK')
            ? 'BLOCK'
            : (collect($checks)->contains(fn (array $check) => $check['level'] === 'WARN') ? 'WARN' : 'PASS');

        $payload = ['status' => $status, 'checks' => $checks];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info("Reservation assignment preflight: {$status}");

            foreach ($checks as $name => $check) {
                $this->line(sprintf('[%s] %s: %s', $check['level'], $name, $check['summary']));
            }
        }

        return $status === 'BLOCK' ? self::FAILURE : self::SUCCESS;
    }

    private function databaseVersion(): array
    {
        $version = DB::selectOne('select version() as version')->version ?? 'unknown';

        return ['level' => 'PASS', 'summary' => (string) $version, 'ids' => []];
    }

    private function pendingMigrations(): array
    {
        $migrator = app('migrator');
        $migrationFiles = $migrator->getMigrationFiles(database_path('migrations'));
        $ran = $migrator->getRepository()->getRan();

        $pending = collect(array_keys($migrationFiles))
            ->diff($ran)
            ->values()
            ->all();

        return [
            'level' => $pending === [] ? 'PASS' : 'WARN',
            'summary' => $pending === [] ? 'No pending migrations reported.' : count($pending).' pending migrations reported.',
            'ids' => $pending,
        ];
    }

    private function incompleteReady(): array
    {
        $ids = DB::table('reservations')
            ->where('status', Reservation::STATUS_READY)
            ->where(function ($query) {
                $query->whereNull('assigned_book_copy_id')
                    ->orWhereNull('pickup_branch_id')
                    ->orWhereNull('ready_at')
                    ->orWhereNull('expires_at');
            })
            ->limit(50)
            ->pluck('id')
            ->all();

        return $this->idsCheck($ids, 'Incomplete READY reservations.', 'All READY reservations are complete.');
    }

    private function duplicateAssignedCopy(): array
    {
        $ids = DB::table('reservations')
            ->select('assigned_book_copy_id')
            ->where('status', Reservation::STATUS_READY)
            ->whereNotNull('assigned_book_copy_id')
            ->groupBy('assigned_book_copy_id')
            ->havingRaw('count(*) > 1')
            ->limit(50)
            ->pluck('assigned_book_copy_id')
            ->all();

        return $this->idsCheck($ids, 'Duplicate READY assigned copy IDs.', 'No duplicate READY assignments.');
    }

    private function activeLoanConflict(): array
    {
        $ids = DB::table('reservations')
            ->join('loans', 'loans.book_copy_id', '=', 'reservations.assigned_book_copy_id')
            ->where('reservations.status', Reservation::STATUS_READY)
            ->whereNull('reservations.fulfilled_at')
            ->whereNull('reservations.cancelled_at')
            ->whereNull('loans.returned_at')
            ->whereIn('loans.status', Loan::ACTIVE_STATUSES)
            ->limit(50)
            ->pluck('reservations.id')
            ->all();

        return $this->idsCheck($ids, 'READY reservations conflict with active loans.', 'No active loan conflicts.');
    }

    private function assignedCopyMismatch(string $column): array
    {
        $ids = DB::table('reservations')
            ->join('book_copies', 'book_copies.id', '=', 'reservations.assigned_book_copy_id')
            ->whereColumn("reservations.{$column}", '<>', "book_copies.{$column}")
            ->limit(50)
            ->pluck('reservations.id')
            ->all();

        return $this->idsCheck($ids, "Assigned copy {$column} mismatch.", "No assigned copy {$column} mismatch.");
    }

    private function assignedCopyBranchMismatch(): array
    {
        $ids = DB::table('reservations')
            ->join('book_copies', 'book_copies.id', '=', 'reservations.assigned_book_copy_id')
            ->where('reservations.status', Reservation::STATUS_READY)
            ->whereColumn('reservations.pickup_branch_id', '<>', 'book_copies.branch_id')
            ->limit(50)
            ->pluck('reservations.id')
            ->all();

        return $this->idsCheck($ids, 'READY pickup branch differs from assigned copy branch.', 'No pickup branch mismatch.');
    }

    private function expiredReady(): array
    {
        $ids = DB::table('reservations')
            ->where('status', Reservation::STATUS_READY)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->limit(50)
            ->pluck('id')
            ->all();

        return [
            'level' => $ids === [] ? 'PASS' : 'WARN',
            'summary' => $ids === [] ? 'No expired READY reservations.' : 'Expired READY reservation IDs: '.implode(', ', $ids),
            'ids' => $ids,
        ];
    }

    private function reservedAtSuspicious(): array
    {
        $ids = DB::table('reservations')
            ->whereColumn('reserved_at', '<>', 'created_at')
            ->limit(50)
            ->pluck('id')
            ->all();

        return [
            'level' => $ids === [] ? 'PASS' : 'WARN',
            'summary' => $ids === [] ? 'No reserved_at drift detected.' : 'reserved_at differs from created_at for IDs: '.implode(', ', $ids),
            'ids' => $ids,
        ];
    }

    private function idsCheck(array $ids, string $badSummary, string $goodSummary): array
    {
        return [
            'level' => $ids === [] ? 'PASS' : 'BLOCK',
            'summary' => $ids === [] ? $goodSummary : $badSummary.' IDs: '.implode(', ', $ids),
            'ids' => $ids,
        ];
    }
}
