<?php

namespace Database\Factories;

use App\Models\BookCopy;
use App\Models\Library;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScanLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'book_copy_id' => BookCopy::factory(),
            'user_id' => User::factory(),
            'scan_value' => strtoupper(fake()->bothify('QR-########')),
            'scan_type' => fake()->randomElement(['info', 'loan', 'return', 'inventory']),
            'result' => fake()->randomElement(['success', 'success', 'success', 'not_found', 'blocked', 'error']),
            'device_info' => fake()->randomElement([
                'Samsung A52',
                'Xiaomi Redmi Note',
                'Chrome on Windows',
                'Firefox on Linux',
            ]),
        ];
    }
}

