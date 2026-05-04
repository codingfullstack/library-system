<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookCopyScanResource extends JsonResource
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
        return [
            'book_copy' => (new BookCopyDetailsResource($this->resource, $this->canManageCopy))->resolve(),
            'book' => $this->book ? [
                'id' => $this->book->id,
                'title' => $this->book->title,
                'isbn' => $this->book->isbn,
            ] : null,
            'status' => $this->status,
            'can_borrow' => $this->status === 'available' && $this->canManageCopy && $this->activeLoan === null,
            'can_return' => $this->status === 'loaned' && $this->canManageCopy,
        ];
    }
}
