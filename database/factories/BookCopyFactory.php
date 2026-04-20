<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookCopyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'book_id' => Book::factory(),
            'branch_id' => Branch::factory(),
            'location_id' => Location::factory(),
            'inventory_code' => strtoupper(fake()->unique()->bothify('INV-######')),
            'qr_code' => strtoupper(fake()->unique()->bothify('QR-########')),
            'barcode' => fake()->optional()->unique()->numerify('###########'),
            'status' => fake()->randomElement(['available', 'available', 'available', 'loaned', 'reserved']),
            'condition_status' => fake()->randomElement(['new', 'good', 'good', 'worn']),
            'acquired_at' => fake()->date(),
            'price' => fake()->randomFloat(2, 5, 100),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}