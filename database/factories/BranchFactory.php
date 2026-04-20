<?php

namespace Database\Factories;

use App\Models\Library;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'name' => fake()->company() . ' Branch',
            'code' => strtoupper(fake()->bothify('BR-###??')),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
        ];
    }
}