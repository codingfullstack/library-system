<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Publisher;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'subtitle' => fake()->optional()->sentence(4),
            'isbn' => fake()->boolean(80) ? fake()->unique()->numerify('978##########') : null,
            'description' => fake()->paragraph(),
            'publisher_id' => Publisher::factory(),
            'category_id' => Category::factory(),
            'publication_year' => fake()->year(),
            'language' => fake()->randomElement(['lt', 'en', 'pl', 'de']),
            'page_count' => fake()->numberBetween(80, 900),
            'edition' => fake()->optional()->randomElement(['1st', '2nd', '3rd']),
            'cover_image' => null,
        ];
    }
}