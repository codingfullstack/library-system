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
     * @return array{created: int, updated: int, skipped: int, details: array<int, array<string, string|int|null>>}
     */
    public function import(User $user, array $rows, ?int $selectedLibraryId = null): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $details = [];

        DB::transaction(function () use ($user, $rows, $selectedLibraryId, &$created, &$updated, &$skipped, &$details): void {
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $branch = $this->resolveBranch($user, $selectedLibraryId, $row, $line);
                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    throw new \RuntimeException('Eilute ' . $line . ': privalomas laukelis name.');
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
                            ? 'Vieta su tokiu kodu jau yra siame filiale.'
                            : 'Vieta su tokiu pavadinimu jau yra siame filiale.',
                    ];
                    continue;
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
                    'message' => 'Sukurta nauja vieta.',
                ];
            }

            $this->recordAuditLogAction->handle(
                $user,
                'locations_imported',
                null,
                sprintf('Importuotos vietos: sukurta %d, praleista %d.', $created, $skipped),
                [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'rows' => count($rows),
                ],
                $selectedLibraryId ?: $user->library_id
            );
        });

        return compact('created', 'updated', 'skipped', 'details');
    }

    private function resolveBranch(User $user, ?int $selectedLibraryId, array $row, int $line): Branch
    {
        $branchCode = trim((string) ($row['branch_code'] ?? ''));
        $branchName = trim((string) ($row['branch_name'] ?? ''));

        if ($branchCode === '' && $branchName === '') {
            throw new \RuntimeException('Eilute ' . $line . ': reikia branch_code arba branch_name.');
        }

        $query = Branch::query();

        if ($user->isSuperAdmin()) {
            if (! $selectedLibraryId) {
                throw new \RuntimeException('Superadmin importui reikia pasirinkti biblioteka.');
            }

            $query->where('library_id', $selectedLibraryId);
        } else {
            $query->where('library_id', $user->library_id);
        }

        $branch = $branchCode !== ''
            ? (clone $query)->where('code', $branchCode)->first()
            : (clone $query)->where('name', $branchName)->first();

        if (! $branch) {
            throw new \RuntimeException('Eilute ' . $line . ': nurodytas filialas nerastas.');
        }

        return $branch;
    }
}
