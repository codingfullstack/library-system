<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primaryCategory = $this->categories->first();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'isbn' => $this->isbn,
            'description' => $this->description,
            'cover_image' => $this->cover_image_url,
            'publication_year' => $this->publication_year,
            'language' => $this->language,
            'page_count' => $this->page_count,
            'copies_count' => $this->copies_count,
            'available_copies_count' => $this->available_copies_count ?? null,
            'loaned_copies_count' => $this->loaned_copies_count ?? null,
            'unavailable_copies_count' => $this->unavailable_copies_count ?? null,
            'active_reservations_count' => $this->active_reservations_count ?? null,

            'publisher' => $this->publisher ? [
                'id' => $this->publisher->id,
                'name' => $this->publisher->name,
            ] : null,

            'category' => $primaryCategory ? [
                'id' => $primaryCategory->id,
                'name' => $primaryCategory->name,
            ] : null,

            'categories' => $this->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                ];
            })->values(),

            'authors' => $this->authors->map(function ($author) {
                return [
                    'id' => $author->id,
                    'name' => $author->name,
                ];
            })->values(),
        ];
    }
}
