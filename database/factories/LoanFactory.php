<?php

namespace Database\Factories;

use App\Models\BookCopy;
use App\Models\Library;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    public function definition(): array
    {
        $borrowedAt = fake()->dateTimeBetween('-60 days', '-1 day');
        $dueAt = (clone $borrowedAt)->modify('+14 days');
        $returned = fake()->boolean(35);

        return [
            'library_id' => Library::factory(),
            'book_copy_id' => BookCopy::factory(),
            'user_id' => User::factory(),
            'issued_by' => null,
            'received_by' => null,
            'borrowed_at' => $borrowedAt,
            'due_at' => $dueAt,
            'returned_at' => $returned ? fake()->dateTimeBetween($borrowedAt, 'now') : null,
            'status' => $returned ? 'returned' : fake()->randomElement(['active', 'overdue']),
            'renewal_count' => fake()->numberBetween(0, 2),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}