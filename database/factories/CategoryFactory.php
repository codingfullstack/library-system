<?php

namespace Database\Factories;

use App\Support\GeneratesSlugs;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'slug' => GeneratesSlugs::from($name, 'kategorija'),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
