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
        return [
            'id' => $this->id,
            'library_id' => $this->library_id,
            'book_copy_id' => $this->book_copy_id,
            'user_id' => $this->user_id,
            'issued_by' => $this->issued_by,
            'received_by' => $this->received_by,
            'borrowed_at' => $this->borrowed_at,
            'due_at' => $this->due_at,
            'returned_at' => $this->returned_at,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'renewal_count' => $this->renewal_count,
            'notes' => $this->notes,
            'is_overdue' => $this->is_overdue,
            'is_due_soon' => $this->isDueSoon(),
            'overdue_days' => $this->overdue_days,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'membership_number' => $this->user->membership_number,
            ] : null,
            'book_copy' => $this->bookCopy ? [
                'id' => $this->bookCopy->id,
                'inventory_code' => $this->bookCopy->inventory_code,
                'status' => $this->bookCopy->status,
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
            'issuer' => $this->issuer ? [
                'id' => $this->issuer->id,
                'name' => $this->issuer->name,
            ] : null,
            'receiver' => $this->receiver ? [
                'id' => $this->receiver->id,
                'name' => $this->receiver->name,
            ] : null,
        ];
    }
}








