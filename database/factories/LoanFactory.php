<?php

namespace Database\Factories;

use App\Models\BookCopy;
use App\Models\Library;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function ($loan): void {
            $copy = $loan->book_copy_id
                ? BookCopy::query()->find($loan->book_copy_id)
                : null;

            if ($copy && $loan->issued_branch_id === null) {
                $loan->issued_branch_id = $copy->branch_id;
            }

            if ($copy && $loan->returned_at !== null && $loan->returned_branch_id === null) {
                $loan->returned_branch_id = $copy->branch_id;
            }
        });
    }

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
            'issued_branch_id' => null,
            'received_by' => null,
            'returned_branch_id' => null,
            'borrowed_at' => $borrowedAt,
            'due_at' => $dueAt,
            'returned_at' => $returned ? fake()->dateTimeBetween($borrowedAt, 'now') : null,
            'status' => $returned ? 'grąžinta' : fake()->randomElement(['aktyvi', 'vėluoja']),
            'renewal_count' => fake()->numberBetween(0, 2),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
