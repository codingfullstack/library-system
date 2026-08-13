<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canViewSensitiveDetails = $request->user()?->canViewSensitiveLoanDetails($this->resource) ?? false;
        $bookCopy = $this->resource->relationLoaded('bookCopy') ? $this->bookCopy : null;
        $canReturn = $bookCopy !== null
            ? (($request->user()?->can('return', $bookCopy) ?? false) && $this->returned_at === null)
            : false;

        $safe = [
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'display_status' => $this->statusLabel(),
            'is_overdue' => $this->is_overdue,
            'is_due_soon' => $this->isDueSoon(),
            'overdue_days' => $this->overdue_days,
            'can_renew' => false,
            'can_return' => $canReturn,
            'book_copy' => $this->bookCopy ? [
                'id' => $this->bookCopy->id,
                'inventory_code' => $this->bookCopy->inventory_code,
                'status' => $this->bookCopy->operationalStatus(),
                'status_label' => $this->bookCopy->statusLabel(),
                'lifecycle_status' => $this->bookCopy->lifecycleStatus(),
                'book' => $this->bookCopy->book ? [
                    'id' => $this->bookCopy->book->id,
                    'title' => $this->bookCopy->book->title,
                    'subtitle' => $this->bookCopy->book->subtitle,
                    'isbn' => $this->bookCopy->book->isbn,
                ] : null,
                'branch' => $this->bookCopy->branch ? [
                    'id' => $this->bookCopy->branch->id,
                    'name' => $this->bookCopy->branch->name,
                ] : null,
                'location' => $this->bookCopy->location ? [
                    'id' => $this->bookCopy->location->id,
                    'name' => $this->bookCopy->location->name,
                    'room' => $this->bookCopy->location->room,
                    'shelf' => $this->bookCopy->location->shelf,
                ] : null,
            ] : null,
        ];

        if (! $canViewSensitiveDetails) {
            return $safe;
        }

        return array_merge([
            'id' => $this->id,
            'library_id' => $this->library_id,
            'book_copy_id' => $this->book_copy_id,
            'user_id' => $this->user_id,
            'issued_by' => $this->issued_by,
            'received_by' => $this->received_by,
            'borrowed_at' => $this->borrowed_at,
            'due_at' => $this->due_at,
            'returned_at' => $this->returned_at,
            'renewal_count' => $this->renewal_count,
            'notes' => $this->notes,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'membership_number' => $this->user->membership_number,
            ] : null,
        ], $safe, [
            'issuer' => $this->issuer ? [
                'id' => $this->issuer->id,
                'name' => $this->issuer->name,
            ] : null,
            'receiver' => $this->receiver ? [
                'id' => $this->receiver->id,
                'name' => $this->receiver->name,
            ] : null,
        ]);
    }
}





