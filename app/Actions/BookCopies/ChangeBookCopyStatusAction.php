<?php

namespace App\Actions\BookCopies;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Reservations\SyncReservationQueueAction;
use App\Models\BookCopy;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        return DB::transaction(function () use ($bookCopy, $toStatus, $changedBy, $reasonCode, $reasonNotes, $attributes): BookCopy {
            $bookCopy = BookCopy::query()
                ->withoutGlobalScope('library')
                ->whereKey($bookCopy->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $bookCopy->loadMissing('book:id,title');

            $fromStatus = $bookCopy->lifecycleStatus();

            if ($fromStatus === null || ! $bookCopy->hasValidLifecycleStatus()) {
                throw ValidationException::withMessages([
                    'target_status' => ['Kopija neturi galiojančios gyvavimo ciklo būsenos. Paleiskite duomenų migraciją.'],
                ]);
            }

            if (! in_array($toStatus, array_keys(BookCopy::lifecycleStatusLabels()), true)) {
                throw new InvalidArgumentException('Nežinoma kopijos gyvavimo ciklo būsena.');
            }

            if ($fromStatus !== $toStatus && ! $bookCopy->canChangeLifecycleTo($toStatus)) {
                throw ValidationException::withMessages([
                    'target_status' => ['Šis kopijos gyvavimo ciklo perėjimas negalimas.'],
                ]);
            }

            if ($fromStatus !== $toStatus && $bookCopy->activeLoan()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'target_status' => ['Negalima keisti kopijos gyvavimo ciklo, kol ji yra aktyviai išduota.'],
                ]);
            }

            if ($fromStatus !== $toStatus || $attributes !== []) {
                $bookCopy->fill(array_merge($attributes, [
                    'lifecycle_status' => $toStatus,
                ]));
                $bookCopy->save();
            }

            $this->releaseReadyReservationIfUnavailable($bookCopy, $toStatus);

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
                    'Kopijos %s gyvavimo ciklas pakeistas iš „%s“ į „%s“.',
                    $bookCopy->inventory_code,
                    BookCopy::lifecycleStatusLabels()[$fromStatus] ?? $fromStatus,
                    BookCopy::lifecycleStatusLabels()[$toStatus] ?? $toStatus
                ),
                [
                    'book_id' => $bookCopy->book_id,
                    'book_title' => $bookCopy->book?->title,
                    'inventory_code' => $bookCopy->inventory_code,
                    'from_status' => $fromStatus,
                    'from_status_label' => BookCopy::lifecycleStatusLabels()[$fromStatus] ?? $fromStatus,
                    'target_status' => $toStatus,
                    'target_status_label' => BookCopy::lifecycleStatusLabels()[$toStatus] ?? $toStatus,
                    'reason_code' => $reasonCode,
                    'reason_notes' => $reasonNotes,
                    'condition_status' => $bookCopy->condition_status,
                    'condition_status_label' => $bookCopy->conditionLabel(),
                ]
            );

            if ($fromStatus !== BookCopy::STATUS_IN_CIRCULATION && $toStatus === BookCopy::STATUS_IN_CIRCULATION) {
                app(SyncReservationQueueAction::class)->handle($bookCopy->library_id, $bookCopy->book_id);
            }

            if (in_array($toStatus, BookCopy::unavailableLifecycleStatuses(), true)) {
                app(SyncReservationQueueAction::class)->handle($bookCopy->library_id, $bookCopy->book_id);
            }

            return $bookCopy->fresh();
        });
    }

    private function releaseReadyReservationIfUnavailable(BookCopy $bookCopy, string $toStatus): void
    {
        if (! in_array($toStatus, BookCopy::unavailableLifecycleStatuses(), true)) {
            return;
        }

        Reservation::query()
            ->where('library_id', $bookCopy->library_id)
            ->where('book_id', $bookCopy->book_id)
            ->where('assigned_book_copy_id', $bookCopy->id)
            ->where('status', Reservation::STATUS_READY)
            ->whereNull('fulfilled_at')
            ->whereNull('cancelled_at')
            ->lockForUpdate()
            ->get()
            ->each(function (Reservation $reservation): void {
                $reservation->update([
                    'status' => Reservation::STATUS_WAITING,
                    'pickup_branch_id' => null,
                    'assigned_book_copy_id' => null,
                    'ready_at' => null,
                    'expires_at' => null,
                ]);
            });
    }
}








