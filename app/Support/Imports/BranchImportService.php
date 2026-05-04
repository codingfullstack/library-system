<?php

namespace App\Support\Imports;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Models\Branch;
use App\Models\Library;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BranchImportService
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
                $library = $this->resolveLibrary($user, $selectedLibraryId, $line);
                $name = trim((string) ($row['name'] ?? ''));
                $code = trim((string) ($row['code'] ?? ''));

                if ($name === '' || $code === '') {
                    throw new \RuntimeException('Eilute ' . $line . ': privalomi laukeliai name ir code.');
                }

                $branch = Branch::query()
                    ->where('library_id', $library->id)
                    ->where('code', $code)
                    ->first();

                if ($branch) {
                    $skipped++;
                    $details[] = [
                        'line' => $line,
                        'status' => 'praleista',
                        'label' => $name,
                        'message' => 'Filialas su tokiu kodu jau yra sioje bibliotekoje.',
                    ];
                    continue;
                }

                $branch = new Branch();

                $branch->fill([
                    'library_id' => $library->id,
                    'name' => $name,
                    'code' => $code,
                    'address' => $row['address'] ?? null,
                    'city' => $row['city'] ?? null,
                ]);

                $branch->save();

                $created++;
                $details[] = [
                    'line' => $line,
                    'status' => 'sukurta',
                    'label' => $name,
                    'message' => 'Sukurtas naujas filialas.',
                ];
            }

            $this->recordAuditLogAction->handle(
                $user,
                'branches_imported',
                null,
                sprintf('Importuoti filialai: sukurta %d, praleista %d.', $created, $skipped),
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

    private function resolveLibrary(User $user, ?int $selectedLibraryId, int $line): Library
    {
        if (! $user->isSuperAdmin()) {
            return Library::query()->findOrFail($user->library_id);
        }

        if (! $selectedLibraryId) {
            throw new \RuntimeException('Superadmin importui reikia pasirinkti biblioteka.');
        }

        return Library::query()->findOrFail($selectedLibraryId);
    }
}
