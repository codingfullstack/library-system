<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Library;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'branch_id' => Branch::factory(),
            'name' => 'Shelf ' . strtoupper(fake()->bothify('?##')),
            'code' => strtoupper(fake()->bothify('LOC-###??')),
            'room' => 'Room ' . fake()->numberBetween(1, 20),
            'shelf' => strtoupper(fake()->bothify('S-##-?')),
            'description' => fake()->optional()->sentence(),
        ];
    }
}