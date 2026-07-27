<?php

namespace App\Console\Commands;

use App\Support\Tenancy\TenantIntegrityAuditor;
use Illuminate\Console\Command;

class AuditTenantIntegrityCommand extends Command
{
    protected $signature = 'tenants:audit-integrity';

    protected $description = 'Audit tenant ownership invariants without mutating data.';

    public function handle(TenantIntegrityAuditor $auditor): int
    {
        $violations = $auditor->violations();

        if ($violations->isEmpty()) {
            $this->info('No tenant integrity violations found.');

            return self::SUCCESS;
        }

        $this->error('Tenant integrity violations found. No data was modified.');
        $violations->each(function ($violation): void {
            $this->line(sprintf(
                '%s#%d %s library_id=%s related=%s#%s',
                $violation->table,
                $violation->id,
                $violation->type,
                $violation->libraryId ?? 'null',
                $violation->relatedTable ?? 'null',
                $violation->relatedId ?? 'null',
            ));
        });

        $this->table(
            ['table', 'id', 'type', 'library_id', 'related_table', 'related_id'],
            $violations->map(fn ($violation) => $violation->toArray())->all()
        );

        return self::FAILURE;
    }
}
