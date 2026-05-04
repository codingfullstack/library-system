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

        return [
            'library_id' => $library->id,
            'book_id' => Book::factory(),
            'branch_id' => $branch->id,
            'location_id' => $location->id,
            'inventory_code' => strtoupper(fake()->unique()->bothify('INV-######')),
            'qr_code' => strtoupper(fake()->unique()->bothify('QR-########')),
            'barcode' => fake()->boolean(50) ? fake()->unique()->numerify('###########') : null,
            'status' => fake()->randomElement([
                BookCopy::STATUS_AVAILABLE,
                BookCopy::STATUS_AVAILABLE,
                BookCopy::STATUS_AVAILABLE,
                BookCopy::STATUS_AVAILABLE,
                BookCopy::STATUS_LOANED,
                BookCopy::STATUS_MAINTENANCE,
                BookCopy::STATUS_DAMAGED,
                BookCopy::STATUS_LOST,
            ]),
            'condition_status' => fake()->randomElement(['new', 'good', 'good', 'worn', 'damaged']),
            'acquired_at' => fake()->date(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
