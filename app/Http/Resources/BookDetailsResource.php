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
        $primaryCategory = $this->categories->first();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'isbn' => $this->isbn,
            'description' => $this->description,
            'edition' => $this->edition,
            'cover_image' => $this->cover_image_url,
            'publication_year' => $this->publication_year,
            'language' => $this->language,
            'page_count' => $this->page_count,
            'copies_count' => $this->copies_count,
            'available_copies_count' => $this->available_copies_count ?? null,
            'is_available' => ((int) ($this->available_copies_count ?? 0)) > 0,
            'can_reserve' => ($request->user()?->hasAnyEffectiveRole(['superadministratorius', 'administratorius', 'darbuotojas', 'narys']) ?? false)
                && (int) ($this->copies_count ?? 0) > 0
                && (int) ($this->available_copies_count ?? 0) === 0,
            'display_status' => ((int) ($this->available_copies_count ?? 0)) > 0
                ? 'Galima'
                : (((int) ($this->copies_count ?? 0)) > 0 ? 'Šiuo metu neprieinama' : 'Nėra egzempliorių'),

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

            'reservations' => $this->whenLoaded(
                'reservations',
                fn () => ReservationResource::collection($this->reservations)->resolve()
            ),

            'book_copies' => $this->bookCopies->map(function ($copy) use ($request) {
                return (new BookCopyDetailsResource(
                    $copy,
                    $request->user()?->can('update', $copy) ?? false
                ))->resolve();
            })->values(),
        ];
    }
}
