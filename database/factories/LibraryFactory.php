<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LibraryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Library',
            'code' => strtoupper(fake()->unique()->bothify('LIB-###??')),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'is_active' => true,
        ];
    }
}