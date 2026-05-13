<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'library_id' => $this->library_id,
            'book_id' => $this->book_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'reserved_at' => $this->reserved_at,
            'expires_at' => $this->expires_at,
            'fulfilled_at' => $this->fulfilled_at,
            'cancelled_at' => $this->cancelled_at,
            'notes' => $this->notes,
            'is_pending' => $this->isPending(),
            'is_current' => $this->isCurrent(),
            'queue_position' => $this->queue_position ?? null,
            'book' => $this->whenLoaded('book', function () {
                return [
                    'id' => $this->book->id,
                    'title' => $this->book->title,
                    'subtitle' => $this->book->subtitle,
                    'isbn' => $this->book->isbn,
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'membership_number' => $this->user->membership_number,
                ];
            }),
            'library' => $this->whenLoaded('library', function () {
                return [
                    'id' => $this->library->id,
                    'name' => $this->library->name,
                ];
            }),
        ];
    }
}








