<?php

namespace App\Actions\ScanLogs;

use App\Models\BookCopy;
use App\Models\ScanLog;
use App\Models\User;

class RecordScanLogAction
{
    public function handle(
        User $user,
        string $scanValue,
        string $scanType,
        string $result,
        ?BookCopy $bookCopy = null,
        ?string $deviceInfo = null,
        ?int $libraryId = null,
    ): ?ScanLog {
        $libraryId ??= $bookCopy?->library_id ?? $user->activeLibraryId();

        if (! $libraryId) {
            return null;
        }

        return ScanLog::create([
            'library_id' => $libraryId,
            'book_copy_id' => $bookCopy?->id,
            'user_id' => $user->id,
            'scan_value' => $scanValue,
            'scan_type' => $scanType,
            'result' => $result,
            'device_info' => $deviceInfo,
        ]);
    }
}








