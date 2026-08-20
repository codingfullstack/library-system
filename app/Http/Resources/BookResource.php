<?php

namespace App\Http\Resources;

use App\Support\Books\BookAvailability;
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
        $availability = app(BookAvailability::class)->forBook(
            $this->resource,
            $request->user(),
            $request->user()?->activeLibraryId()
        );

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
            'total_copies_count' => $availability['total_copies_count'],
            'copies_count' => $availability['copies_count'],
            'available_copies_count' => $availability['available_copies_count'],
            'loaned_copies_count' => $this->loaned_copies_count ?? null,
            'unavailable_copies_count' => $this->unavailable_copies_count ?? null,
            'active_reservations_count' => $availability['active_reservations_count'],
            'ready_reservations_count' => $availability['ready_reservations_count'],
            'waiting_reservations_count' => $availability['waiting_reservations_count'],
            'is_available' => $availability['is_available'],
            'can_reserve' => $availability['can_reserve'],
            'cannot_reserve_reason' => $availability['cannot_reserve_reason'],
            'display_status' => $availability['display_status'],
            'availability_status' => $availability['availability_status'],
            'availability_label' => $availability['availability_label'],
            'availability_reason' => $availability['availability_reason'],
            'has_waiting_queue' => $availability['has_waiting_queue'],
            'has_reservation_queue' => $availability['has_reservation_queue'],
            'reservation_queue_size' => $availability['reservation_queue_size'],
            'current_user_reservation' => $availability['current_user_reservation'],
            'current_user_queue_position' => $availability['current_user_queue_position'],

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
