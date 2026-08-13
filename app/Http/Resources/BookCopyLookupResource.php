<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookCopyLookupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'library_id' => $this->library_id,
            'inventory_code' => $this->inventory_code,
            'qr_code' => $this->qr_code,
            'status' => $this->operationalStatus(),
            'status_label' => $this->statusLabel(),
            'operational_status' => $this->operationalStatus(),
            'operational_label' => $this->operationalStatusLabel(),
            'lifecycle_status' => $this->lifecycleStatus(),
            'lifecycle_label' => $this->lifecycleStatusLabel(),
            'book' => $this->book ? [
                'id' => $this->book->id,
                'title' => $this->book->title,
            ] : null,
            'branch' => $this->branch?->name,
        ];
    }
}








