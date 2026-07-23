<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Notifications\NotificationType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReservationQueueDebugService
{
    public function __construct(
        private readonly ReservationQueueService $queueService,
    ) {}

    public function enabled(?User $user = null): bool
    {
        return app()->environment('local')
            && $user !== null
            && $user->hasAnyEffectiveRole(['superadministratorius', 'administratorius', 'darbuotojas'], $user->activeLibraryId());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forBook(Book $book, ?User $user = null): ?array
    {
        if (! $this->enabled($user)) {
            return null;
        }

        $libraryId = $this->debugLibraryId($book, $user);

        if ($libraryId === null) {
            return [
                'book' => $this->bookPayload($book, null),
                'current_time' => now()->toDateTimeString(),
                'error' => 'No library context could be resolved for this book debug view.',
            ];
        }

        $positionsMap = $this->queueService->getPositionsForBook($libraryId, $book->id);
        $reservations = Reservation::query()
            ->withoutGlobalScope('library')
            ->where('library_id', $libraryId)
            ->where('book_id', $book->id)
            ->with(['user:id,name', 'branch:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $orderedReservationIds = $reservations->pluck('id')->values();

        return [
            'book' => $this->bookPayload($book, $libraryId),
            'current_time' => now()->toDateTimeString(),
            'positions_map' => $positionsMap,
            'queue_sql' => $this->queueService->pendingReservationsQuery($libraryId, $book->id)->toSql(),
            'queue_bindings' => $this->queueService->pendingReservationsQuery($libraryId, $book->id)->getBindings(),
            'reservation_order' => $reservations
                ->map(function (Reservation $reservation, int $index) use ($positionsMap, $orderedReservationIds) {
                    return $this->reservationOrderPayload($reservation, $positionsMap, [
                        'previous_reservation_id' => $index > 0 ? $orderedReservationIds[$index - 1] : null,
                        'next_reservation_id' => $orderedReservationIds[$index + 1] ?? null,
                    ]);
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logSnapshot(string $event, int $libraryId, int $bookId, array $context = []): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $payload = array_merge([
            'event' => $event,
            'library_id' => $libraryId,
            'book_id' => $bookId,
            'positions' => $this->queueService->getPositionsForBook($libraryId, $bookId),
            'active_reservation_ids' => $this->activeReservationIds($libraryId, $bookId),
            'ready_reservation_ids' => $this->readyReservationIds($libraryId, $bookId),
            'final_reservation_ids' => $this->finalReservationIds($libraryId, $bookId),
        ], $context);

        Log::debug('Reservation queue snapshot', $payload);
    }

    /**
     * @param  array<int, int>  $oldPositions
     * @param  array<int, int>  $newPositions
     * @param  array<int, array<string, int>>  $notifications
     * @param  array<string, mixed>  $context
     */
    public function rememberQueueChange(
        int $libraryId,
        int $bookId,
        string $event,
        array $oldPositions,
        array $newPositions,
        array $notifications,
        array $context = []
    ): void {
        if (! app()->environment('local')) {
            return;
        }

        $removedReservationIds = collect($oldPositions)
            ->keys()
            ->diff(collect($newPositions)->keys())
            ->values()
            ->all();

        $payload = array_merge([
            'event' => $event,
            'library_id' => $libraryId,
            'book_id' => $bookId,
            'created_at' => now()->toDateTimeString(),
            'before' => $oldPositions,
            'after' => $newPositions,
            'removed_reservation_ids' => $removedReservationIds,
            'notifications' => $notifications,
            'active_reservation_ids' => $this->activeReservationIds($libraryId, $bookId),
            'ready_reservation_ids' => $this->readyReservationIds($libraryId, $bookId),
            'final_reservation_ids' => $this->finalReservationIds($libraryId, $bookId),
        ], $context);

        Cache::put($this->cacheKey($libraryId, $bookId), $payload, now()->addMinutes(30));
        Log::debug('Reservation queue change snapshot', $payload);
    }

    public function cacheKey(int $libraryId, int $bookId): string
    {
        return "reservation_queue_debug:{$libraryId}:{$bookId}";
    }

    private function debugLibraryId(Book $book, ?User $user): ?int
    {
        $activeLibraryId = $user?->activeLibraryId();

        if ($activeLibraryId !== null) {
            return (int) $activeLibraryId;
        }

        return BookCopy::query()
            ->withoutGlobalScope('library')
            ->where('book_id', $book->id)
            ->value('library_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function bookPayload(Book $book, ?int $libraryId): array
    {
        return [
            'id' => $book->id,
            'title' => $book->title,
            'library_id' => $libraryId,
        ];
    }

    /**
     * @param  array<int, int>  $positionsMap
     * @return array<string, mixed>
     */
    private function reservationPayload(Reservation $reservation, array $positionsMap, bool $includeRawAttribute = false): array
    {
        return [
            'id' => $reservation->id,
            'user_id' => $reservation->user_id,
            'user_name' => $reservation->user?->name,
            'library_id' => $reservation->library_id,
            'book_id' => $reservation->book_id,
            'scope' => $reservation->scope,
            'branch_id' => $reservation->branch_id,
            'branch_name' => $reservation->branch?->name,
            'status' => $reservation->status,
            'is_pending' => $reservation->isPending(),
            'is_ready' => $reservation->isReady(),
            'expires_at' => $reservation->expires_at?->toDateTimeString(),
            'reserved_at' => $reservation->reserved_at?->toDateTimeString(),
            'created_at' => $reservation->created_at?->toDateTimeString(),
            'updated_at' => $reservation->updated_at?->toDateTimeString(),
            'fulfilled_at' => $reservation->fulfilled_at?->toDateTimeString(),
            'cancelled_at' => $reservation->cancelled_at?->toDateTimeString(),
            'ready_at' => $reservation->getAttribute('ready_at'),
            'assigned_branch_id' => $reservation->getAttribute('assigned_branch_id'),
            'pickup_branch_id' => $reservation->getAttribute('pickup_branch_id'),
            'queue_position_attribute' => $includeRawAttribute ? $reservation->getAttribute('queue_position') : null,
            'computed_position' => $positionsMap[(int) $reservation->id] ?? null,
        ];
    }

    /**
     * @param  array<int, int>  $positionsMap
     * @param  array<string, int|null>  $sequence
     * @return array<string, mixed>
     */
    private function reservationOrderPayload(Reservation $reservation, array $positionsMap, array $sequence): array
    {
        return [
            'id' => $reservation->id,
            'previous_reservation_id' => $sequence['previous_reservation_id'],
            'next_reservation_id' => $sequence['next_reservation_id'],
            'computed_position' => $positionsMap[(int) $reservation->id] ?? null,
            'user_id' => $reservation->user_id,
            'user_name' => $reservation->user?->name,
            'reservation_owner_name' => $reservation->user?->name,
            'scope' => $reservation->scope,
            'branch_id' => $reservation->branch_id,
            'branch_name' => $reservation->branch?->name,
            'status' => $reservation->status,
            'is_active_queue' => isset($positionsMap[(int) $reservation->id]),
            'is_ready' => $reservation->isReady(),
            'displayed_reservation_date' => $reservation->created_at?->toDateTimeString(),
            'queue_order_timestamp' => $reservation->created_at?->toDateTimeString(),
            'reserved_at' => $reservation->reserved_at?->toDateTimeString(),
            'created_at' => $reservation->created_at?->toDateTimeString(),
            'updated_at' => $reservation->updated_at?->toDateTimeString(),
            'expires_at' => $reservation->expires_at?->toDateTimeString(),
            'fulfilled_at' => $reservation->fulfilled_at?->toDateTimeString(),
            'cancelled_at' => $reservation->cancelled_at?->toDateTimeString(),
        ];
    }

    /**
     * @param  array<int, int>  $positionsMap
     * @return array<int, array<string, mixed>>
     */
    private function serviceableByBranch(int $libraryId, int $bookId, array $positionsMap): array
    {
        return Branch::query()
            ->where('library_id', $libraryId)
            ->whereHas('bookCopies', fn ($query) => $query
                ->withoutGlobalScope('library')
                ->where('book_id', $bookId))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (Branch $branch) use ($libraryId, $bookId, $positionsMap) {
                $reservations = Reservation::query()
                    ->where('library_id', $libraryId)
                    ->where('book_id', $bookId)
                    ->active()
                    ->where(function ($query) use ($branch) {
                        $query
                            ->where(function ($libraryScopeQuery) {
                                $libraryScopeQuery
                                    ->where('scope', Reservation::SCOPE_LIBRARY)
                                    ->whereNull('branch_id');
                            })
                            ->orWhere(function ($branchScopeQuery) use ($branch) {
                                $branchScopeQuery
                                    ->where('scope', Reservation::SCOPE_BRANCH)
                                    ->where('branch_id', $branch->id);
                            });
                    })
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->with(['user:id,name', 'branch:id,name'])
                    ->get()
                    ->values()
                    ->map(fn (Reservation $reservation, int $index) => [
                        'id' => $reservation->id,
                        'global_position' => $positionsMap[(int) $reservation->id] ?? null,
                        'serviceable_index' => $index + 1,
                        'scope' => $reservation->scope,
                        'branch_id' => $reservation->branch_id,
                        'status' => $reservation->status,
                        'expires_at' => $reservation->expires_at?->toDateTimeString(),
                    ])
                    ->all();

                return [
                    $branch->id => [
                        'branch_name' => $branch->name,
                        'reservations' => $reservations,
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bookCopies(int $libraryId, int $bookId): array
    {
        return BookCopy::query()
            ->withoutGlobalScope('library')
            ->where('library_id', $libraryId)
            ->where('book_id', $bookId)
            ->with(['branch:id,name', 'activeLoan:id,book_copy_id,user_id,due_at,status,returned_at'])
            ->orderBy('inventory_code')
            ->get()
            ->map(fn (BookCopy $copy) => [
                'id' => $copy->id,
                'inventory_code' => $copy->inventory_code,
                'branch_id' => $copy->branch_id,
                'branch_name' => $copy->branch?->name,
                'status' => $copy->status,
                'condition_status' => $copy->condition_status,
                'active_loan_id' => $copy->activeLoan?->id,
                'active_loan_user_id' => $copy->activeLoan?->user_id,
                'active_loan_due_at' => $copy->activeLoan?->due_at?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function latestNotifications(int $libraryId, int $bookId): array
    {
        return \Illuminate\Notifications\DatabaseNotification::query()
            ->whereIn('type', [
                NotificationType::RESERVATION_CREATED->value,
                NotificationType::RESERVATION_QUEUE_CHANGED->value,
                NotificationType::RESERVATION_READY->value,
                NotificationType::RESERVATION_FULFILLED->value,
            ])
            ->where('data->metadata->library_id', $libraryId)
            ->where('data->metadata->book_id', $bookId)
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($notification) {
                $metadata = $notification->data['metadata'] ?? [];

                return [
                    'id' => $notification->id,
                    'user_id' => $notification->notifiable_id,
                    'type' => $notification->type,
                    'reservation_id' => $metadata['reservation_id'] ?? $notification->data['related_id'] ?? null,
                    'old_position' => $metadata['old_queue_position'] ?? $metadata['old_position'] ?? null,
                    'new_position' => $metadata['new_queue_position'] ?? $metadata['new_position'] ?? $metadata['queue_position'] ?? null,
                    'created_at' => $notification->created_at?->toDateTimeString(),
                    'data' => $notification->data,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function activeReservationIds(int $libraryId, int $bookId): array
    {
        return $this->queueService
            ->pendingReservationsQuery($libraryId, $bookId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function readyReservationIds(int $libraryId, int $bookId): array
    {
        return $this->queueService
            ->activeReservationsQuery($libraryId, $bookId)
            ->where('status', Reservation::STATUS_READY)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function finalReservationIds(int $libraryId, int $bookId): array
    {
        return Reservation::query()
            ->withoutGlobalScope('library')
            ->where('library_id', $libraryId)
            ->where('book_id', $bookId)
            ->where(function ($query) {
                $query
                    ->whereIn('status', [
                        Reservation::STATUS_FULFILLED,
                        Reservation::STATUS_CANCELLED,
                        Reservation::STATUS_EXPIRED,
                    ])
                    ->orWhereNotNull('fulfilled_at')
                    ->orWhereNotNull('cancelled_at');
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
