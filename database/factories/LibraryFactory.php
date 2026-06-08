<?php

namespace Database\Factories;

use App\Support\GeneratesSlugs;
use Illuminate\Database\Eloquent\Factories\Factory;

class LibraryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company().' Library';

        return [
            'name' => $name,
            'slug' => GeneratesSlugs::from($name, 'biblioteka'),
            'code' => strtoupper(fake()->unique()->bothify('LIB-###??')),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'is_active' => true,
            'is_public' => true,
        ];
    }
}
