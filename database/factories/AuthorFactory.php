<?php

namespace Database\Factories;

use App\Support\GeneratesSlugs;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuthorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'slug' => GeneratesSlugs::from(fake()->unique()->name(), 'autorius'),
            'bio' => fake()->optional()->paragraph(),
        ];
    }
}
