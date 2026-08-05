<?php

namespace Database\Seeders\Support;

use App\Models\Library;
use Illuminate\Support\Facades\DB;

class DemoDatasetMarker
{
    public function completed(Library $library, string $datasetKey, string $version): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('demo_dataset_markers')) {
            return false;
        }

        return DB::table('demo_dataset_markers')
            ->where('dataset_key', $datasetKey)
            ->where('library_id', $library->id)
            ->where('version', $version)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function markCompleted(Library $library, string $datasetKey, string $version, array $metadata = []): void
    {
        if (! DB::getSchemaBuilder()->hasTable('demo_dataset_markers')) {
            return;
        }

        $now = now();

        DB::table('demo_dataset_markers')->updateOrInsert(
            [
                'dataset_key' => $datasetKey,
                'library_id' => $library->id,
                'version' => $version,
            ],
            [
                'completed_at' => $now,
                'metadata' => json_encode($metadata),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
}
