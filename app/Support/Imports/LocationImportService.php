<?php

namespace App\Support\Imports;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LocationImportService
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLogAction,
    ) {
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     * @return array{created: int, updated: int, skipped: int, failed: int, details: array<int, array<string, string|int|null>>}
     */
    public function import(User $user, array $rows, ?int $selectedLibraryId = null): array
    {
        $this->ensureLibrarySelected($user, $selectedLibraryId);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $details = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            try {
                DB::transaction(function () use ($user, $row, $selectedLibraryId, $line, &$created, &$skipped, &$details): void {
                    $branch = $this->resolveBranch($user, $selectedLibraryId, $row);
                    $name = trim((string) ($row['name'] ?? ''));

                    if ($name === '') {
                        throw new \RuntimeException('Privalomas laukelis name.');
                    }

                    $code = trim((string) ($row['code'] ?? ''));

                    $location = $code !== ''
                        ? Location::query()->where('branch_id', $branch->id)->where('code', $code)->first()
                        : Location::query()->where('branch_id', $branch->id)->where('name', $name)->first();

                    if ($location) {
                        $skipped++;
                        $details[] = [
                            'line' => $line,
                            'status' => 'praleista',
                            'label' => $name,
                            'message' => $code !== ''
                                ? 'Vieta su tokiu kodu jau yra šiame filiale.'
                                : 'Vieta su tokiu pavadinimu jau yra šiame filiale.',
                        ];

                        return;
                    }

                    $location = new Location();

                    $location->fill([
                        'library_id' => $branch->library_id,
                        'branch_id' => $branch->id,
                        'name' => $name,
                        'code' => $code !== '' ? $code : null,
                        'room' => $row['room'] ?? null,
                        'shelf' => $row['shelf'] ?? null,
                        'description' => $row['description'] ?? null,
                    ]);

                    $location->save();

                    $created++;
                    $details[] = [
                        'line' => $line,
                        'status' => 'sukurta',
                        'label' => $name,
                        'message' => 'Sukurta naują vietą.',
                    ];
                });
            } catch (\Throwable $exception) {
                $failed++;
                $details[] = [
                    'line' => $line,
                    'status' => 'klaida',
                    'label' => $this->rowLabel($row),
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $this->recordAuditLogAction->handle(
            $user,
            'locations_imported',
            null,
            sprintf('Importuotos vietos: sukurta %d, praleista %d, klaidų %d.', $created, $skipped, $failed),
            [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => $failed,
                'rows' => count($rows),
            ],
            $selectedLibraryId ?: $user->activeLibraryId()
        );

        return compact('created', 'updated', 'skipped', 'failed', 'details');
    }

    private function resolveBranch(User $user, ?int $selectedLibraryId, array $row): Branch
    {
        $branchCode = trim((string) ($row['branch_code'] ?? ''));
        $branchName = trim((string) ($row['branch_name'] ?? ''));

        if ($branchCode === '' && $branchName === '') {
            throw new \RuntimeException('Reikia branch_code arba branch_name.');
        }

        $query = Branch::query();

        if ($user->isSuperAdmin()) {
            $query->where('library_id', $selectedLibraryId);
        } else {
            $query->where('library_id', $user->activeLibraryId());
        }

        $branch = $branchCode !== ''
            ? (clone $query)->where('code', $branchCode)->first()
            : (clone $query)->where('name', $branchName)->first();

        if (! $branch) {
            throw new \RuntimeException('Nurodytas filialas nerastas.');
        }

        return $branch;
    }

    private function ensureLibrarySelected(User $user, ?int $selectedLibraryId): void
    {
        if ($user->isSuperAdmin() && ! $selectedLibraryId) {
            throw new \RuntimeException('Superadmin importui reikia pasirinkti biblioteka.');
        }
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function rowLabel(array $row): string
    {
        $name = trim((string) ($row['name'] ?? ''));
        $code = trim((string) ($row['code'] ?? ''));
        $branch = trim((string) ($row['branch_code'] ?? $row['branch_name'] ?? ''));

        return $name !== '' ? $name : ($code !== '' ? $code : ($branch !== '' ? $branch : '-'));
    }
}








