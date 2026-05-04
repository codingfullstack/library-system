<?php

namespace App\Actions\BookCopies;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Models\BookCopy;
use App\Models\User;
use InvalidArgumentException;

class ChangeBookCopyStatusAction
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function handle(
        BookCopy $bookCopy,
        string $toStatus,
        ?User $changedBy,
        string $reasonCode,
        ?string $reasonNotes = null,
        array $attributes = []
    ): BookCopy {
        $fromStatus = $bookCopy->status;

        if (! in_array($toStatus, array_keys(BookCopy::statusLabels()), true)) {
            throw new InvalidArgumentException('Nezinoma egzemplioriaus busena.');
        }

        if ($fromStatus !== $toStatus || $attributes !== []) {
            $bookCopy->fill(array_merge($attributes, [
                'status' => $toStatus,
            ]));
            $bookCopy->save();
        }

        $bookCopy->statusHistories()->create([
            'changed_by' => $changedBy?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason_code' => $reasonCode,
            'reason_notes' => $reasonNotes,
            'changed_at' => now(),
        ]);

        app(RecordAuditLogAction::class)->handle(
            $changedBy,
            'book_copy_status_changed',
            $bookCopy,
            sprintf(
                'Egzemplioriaus %s statusas pakeistas is "%s" i "%s".',
                $bookCopy->inventory_code,
                BookCopy::statusLabels()[$fromStatus] ?? $fromStatus,
                BookCopy::statusLabels()[$toStatus] ?? $toStatus
            ),
            [
                'book_id' => $bookCopy->book_id,
                'book_title' => $bookCopy->book?->title,
                'inventory_code' => $bookCopy->inventory_code,
                'from_status' => $fromStatus,
                'from_status_label' => BookCopy::statusLabels()[$fromStatus] ?? $fromStatus,
                'target_status' => $toStatus,
                'target_status_label' => BookCopy::statusLabels()[$toStatus] ?? $toStatus,
                'reason_code' => $reasonCode,
                'reason_notes' => $reasonNotes,
            ]
        );

        return $bookCopy->fresh();
    }
}
