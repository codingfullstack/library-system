<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Library;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $reservedAt = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'library_id' => Library::factory(),
            'book_id' => Book::factory(),
            'user_id' => User::factory(),
            'status' => fake()->randomElement([
                Reservation::STATUS_RESERVED,
                Reservation::STATUS_FULFILLED,
                Reservation::STATUS_CANCELLED,
                Reservation::STATUS_EXPIRED,
            ]),
            'reserved_at' => $reservedAt,
            'expires_at' => fake()->optional()->dateTimeBetween($reservedAt, '+10 days'),
            'fulfilled_at' => null,
            'cancelled_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
