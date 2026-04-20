<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookDetailsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'isbn' => $this->isbn,
            'description' => $this->description,
            'publication_year' => $this->publication_year,
            'language' => $this->language,
            'page_count' => $this->page_count,
            'copies_count' => $this->copies_count,

            'publisher' => $this->publisher ? [
                'id' => $this->publisher->id,
                'name' => $this->publisher->name,
            ] : null,

            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,

            'authors' => $this->authors->map(function ($author) {
                return [
                    'id' => $author->id,
                    'name' => $author->name,
                ];
            })->values(),

            'book_copies' => $this->bookCopies->map(function ($copy) {
                return [
                    'id' => $copy->id,
                    'inventory_code' => $copy->inventory_code,
                    'status' => $copy->status ?? null,
                    'branch' => $copy->branch ? [
                        'id' => $copy->branch->id,
                        'name' => $copy->branch->name,
                    ] : null,
                    'location' => $copy->location ? [
                        'id' => $copy->location->id,
                        'name' => $copy->location->name,
                        'room' => $copy->location->room,
                        'shelf' => $copy->location->shelf,
                    ] : null,
                    'active_loan' => $copy->activeLoan ? [
                        'id' => $copy->activeLoan->id,
                        'status' => $copy->activeLoan->status,
                        'due_at' => $copy->activeLoan->due_at,
                        'borrowed_at' => $copy->activeLoan->borrowed_at,
                        'returned_at' => $copy->activeLoan->returned_at,
                        'user' => $copy->activeLoan->user ? [
                            'id' => $copy->activeLoan->user->id,
                            'name' => $copy->activeLoan->user->name,
                            'email' => $copy->activeLoan->user->email,
                            'membership_number' => $copy->activeLoan->user->membership_number,
                        ] : null,
                    ] : null,
                ];
            })->values(),
        ];
    }
}