<?php

namespace App\Http\Resources;

use App\Support\Books\BookAvailability;
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
            'edition' => $this->edition,
            'cover_image' => $this->cover_image_url,
            'publication_year' => $this->publication_year,
            'language' => $this->language,
            'page_count' => $this->page_count,
            'total_copies_count' => $availability['total_copies_count'],
            'copies_count' => $availability['copies_count'],
            'available_copies_count' => $availability['available_copies_count'],
            'branch_availability' => $this->branchAvailability(),
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function branchAvailability(): array
    {
        return $this->bookCopies
            ->groupBy('branch_id')
            ->map(function ($copies) {
                $branch = $copies->first()->branch;

                return [
                    'branch_id' => $branch?->id,
                    'branch_name' => $branch?->name ?? 'Nepriskirtas filialas',
                    'total_copies_count' => $copies->count(),
                    'available_copies_count' => $copies
                        ->filter(fn ($copy) => $copy->operationalStatus() === 'laisva')
                        ->count(),
                ];
            })
            ->sortBy('branch_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
