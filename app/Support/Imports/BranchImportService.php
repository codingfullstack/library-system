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
     * @return array{created: int, updated: int, skipped: int, failed: int, details: array<int, array<string, string|int|null>>}
     */
    public function import(User $user, array $rows, ?int $selectedLibraryId = null): array
    {
        $this->resolveLibrary($user, $selectedLibraryId);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $details = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            try {
                DB::transaction(function () use ($user, $row, $selectedLibraryId, $line, &$created, &$skipped, &$details): void {
                    $library = $this->resolveLibrary($user, $selectedLibraryId);
                    $name = trim((string) ($row['name'] ?? ''));
                    $code = trim((string) ($row['code'] ?? ''));

                    if ($name === '' || $code === '') {
                        throw new \RuntimeException('Privalomi laukeliai name ir code.');
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
                            'message' => 'Filialas su tokiu kodu jau yra šioje bibliotekoje.',
                        ];

                        return;
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
            'branches_imported',
            null,
            sprintf('Importuoti filialai: sukurta %d, praleista %d, klaidų %d.', $created, $skipped, $failed),
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

    private function resolveLibrary(User $user, ?int $selectedLibraryId): Library
    {
        if (! $user->isSuperAdmin()) {
            return Library::query()->findOrFail($user->activeLibraryId());
        }

        if (! $selectedLibraryId) {
            throw new \RuntimeException('Superadmin importui reikia pasirinkti biblioteka.');
        }

        return Library::query()->findOrFail($selectedLibraryId);
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function rowLabel(array $row): string
    {
        $name = trim((string) ($row['name'] ?? ''));
        $code = trim((string) ($row['code'] ?? ''));

        return $name !== '' ? $name : ($code !== '' ? $code : '-');
    }
}








