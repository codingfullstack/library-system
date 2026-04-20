<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Grožinė literatūra',
            'Istorija',
            'IT',
            'Psichologija',
            'Mokslas',
            'Vaikų literatūra',
            'Biografijos',
            'Fantastika',
            'Romanai',
            'Verslas',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
        ];
    }
}