<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\ReservationQueueService;

class BookCopyDetailsResource extends JsonResource
{
    public function __construct($resource, protected bool $canManageCopy)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canViewOperationalDetails = $this->canManageCopy || $user?->isSuperAdmin();
        $canViewOwnLoan = $this->activeLoan && $this->activeLoan->user_id === $user?->id;
        $currentReservation = null;

        if ($canViewOperationalDetails && $this->book_id && $this->library_id) {
            $currentReservation = app(ReservationQueueService::class)
                ->getEligibleReservationForCopy($this->resource);

            $currentReservation?->loadMissing([
                'pickupBranch:id,name',
                'user:id,name,email,membership_number',
            ]);
        }

        return [
            'id' => $this->id,
            'library_id' => $this->library_id,
            'inventory_code' => $this->inventory_code,
            'qr_code' => $this->qr_code,
            'barcode' => $this->barcode,
            'status' => $this->status,
            'condition_status' => $this->condition_status,
            'acquired_at' => $this->acquired_at,
            'notes' => $canViewOperationalDetails ? $this->notes : null,
            'status_history' => $canViewOperationalDetails && $this->statusHistories
                ? $this->statusHistories->map(fn ($history) => [
                    'id' => $history->id,
                    'from_status' => $history->from_status,
                    'to_status' => $history->to_status,
                    'reason_code' => $history->reason_code,
                    'reason_label' => $history->reasonLabel(),
                    'reason_notes' => $history->reason_notes,
                    'changed_at' => $history->changed_at,
                    'changed_by' => $history->user ? [
                        'id' => $history->user->id,
                        'name' => $history->user->name,
                    ] : null,
                ])->values()
                : [],
            'book' => $this->book ? [
                'id' => $this->book->id,
                'title' => $this->book->title,
                'subtitle' => $this->book->subtitle,
                'isbn' => $this->book->isbn,
            ] : null,
            'branch' => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null,
            'location' => $this->location ? [
                'id' => $this->location->id,
                'name' => $this->location->name,
                'room' => $this->location->room,
                'shelf' => $this->location->shelf,
            ] : null,
            'active_loan' => ($canViewOperationalDetails || $canViewOwnLoan) && $this->activeLoan ? [
                'id' => $this->activeLoan->id,
                'status' => $this->activeLoan->status,
                'borrowed_at' => $this->activeLoan->borrowed_at,
                'due_at' => $this->activeLoan->due_at,
                'returned_at' => $this->activeLoan->returned_at,
                'renewal_count' => $this->activeLoan->renewal_count,
                'notes' => $canViewOperationalDetails ? $this->activeLoan->notes : null,
                'is_overdue' => $this->activeLoan->is_overdue,
                'overdue_days' => $this->activeLoan->overdue_days,
                'user' => $canViewOperationalDetails && $this->activeLoan->user ? [
                    'id' => $this->activeLoan->user->id,
                    'name' => $this->activeLoan->user->name,
                    'email' => $this->activeLoan->user->email,
                    'membership_number' => $this->activeLoan->user->membership_number,
                ] : null,
                'issued_by' => $canViewOperationalDetails && $this->activeLoan->issuer ? [
                    'id' => $this->activeLoan->issuer->id,
                    'name' => $this->activeLoan->issuer->name,
                    'email' => $this->activeLoan->issuer->email,
                ] : null,
                'received_by' => $canViewOperationalDetails && $this->activeLoan->receiver ? [
                    'id' => $this->activeLoan->receiver->id,
                    'name' => $this->activeLoan->receiver->name,
                    'email' => $this->activeLoan->receiver->email,
                ] : null,
            ] : null,
            'current_reservation' => $currentReservation
                ? (new ReservationResource($currentReservation))->resolve()
                : null,
            'can_borrow' => $this->status === 'laisva' && $this->canManageCopy && $this->activeLoan === null,
            'can_return' => $this->status === 'išduota' && $this->canManageCopy,
            'can_manage' => $this->canManageCopy,
            'available_lifecycle_transitions' => method_exists($this->resource, 'availableLifecycleTransitions')
                ? $this->availableLifecycleTransitions()
                : [],
        ];
    }

}



