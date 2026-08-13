<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookCopyFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (BookCopy $copy) {
            if (! $copy->library_id) {
                return;
            }

            $branch = $copy->branch_id
                ? Branch::query()->find($copy->branch_id)
                : null;

            if (! $branch || (int) $branch->library_id !== (int) $copy->library_id) {
                $branch = Branch::query()
                    ->where('library_id', $copy->library_id)
                    ->orderBy('id')
                    ->first()
                    ?: Branch::factory()->create([
                        'library_id' => $copy->library_id,
                    ]);

                $copy->branch_id = $branch->id;
                $copy->location_id = null;
            }

            $location = $copy->location_id
                ? Location::query()->find($copy->location_id)
                : null;

            if (! $location || (int) $location->library_id !== (int) $copy->library_id || (int) $location->branch_id !== (int) $copy->branch_id) {
                $location = Location::factory()->create([
                    'library_id' => $copy->library_id,
                    'branch_id' => $copy->branch_id,
                ]);

                $copy->location_id = $location->id;
            }
        });
    }

    public function definition(): array
    {
        $library = Library::factory()->create();
        $branch = Branch::factory()->create([
            'library_id' => $library->id,
        ]);
        $location = Location::factory()->create([
            'library_id' => $library->id,
            'branch_id' => $branch->id,
        ]);

        $lifecycleStatus = fake()->randomElement([
            BookCopy::STATUS_AVAILABLE,
            BookCopy::STATUS_AVAILABLE,
            BookCopy::STATUS_AVAILABLE,
            BookCopy::STATUS_PREPARING,
            BookCopy::STATUS_AVAILABLE,
            BookCopy::STATUS_MAINTENANCE,
            BookCopy::STATUS_LOST,
        ]);

        return [
            'library_id' => $library->id,
            'book_id' => Book::factory(),
            'branch_id' => $branch->id,
            'location_id' => $location->id,
            'inventory_code' => strtoupper(fake()->unique()->bothify('INV-######')),
            'qr_code' => strtoupper(fake()->unique()->bothify('QR-########')),
            'barcode' => fake()->boolean(50) ? fake()->unique()->numerify('###########') : null,
            'status' => $lifecycleStatus,
            'condition_status' => fake()->randomElement([
                BookCopy::CONDITION_NEW,
                BookCopy::CONDITION_GOOD,
                BookCopy::CONDITION_GOOD,
                BookCopy::CONDITION_WORN,
            ]),
            'acquired_at' => fake()->date(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
