<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (Reservation $reservation): void {
            if ($reservation->status === Reservation::STATUS_WAITING) {
                $reservation->pickup_branch_id = null;
                $reservation->ready_at = null;
                $reservation->expires_at = null;
                $reservation->fulfilled_at = null;
                $reservation->cancelled_at = null;

                return;
            }

            if ($reservation->status === Reservation::STATUS_READY) {
                if (! $reservation->pickup_branch_id) {
                    $reservation->pickup_branch_id = $reservation->branch_id
                        ?: Branch::query()
                            ->where('library_id', $reservation->library_id)
                            ->orderBy('id')
                            ->value('id')
                        ?: Branch::factory()->create([
                            'library_id' => $reservation->library_id,
                        ])->id;
                }

                if (! $reservation->assigned_book_copy_id) {
                    $reservation->assigned_book_copy_id = BookCopy::factory()->create([
                        'library_id' => $reservation->library_id,
                        'book_id' => $reservation->book_id,
                        'branch_id' => $reservation->pickup_branch_id,
                        'status' => BookCopy::STATUS_AVAILABLE,
                    ])->id;
                }

                $reservation->ready_at ??= $reservation->reserved_at ?: now();
                $reservation->expires_at ??= now()->addDays(14);
                $reservation->fulfilled_at = null;
                $reservation->cancelled_at = null;

                return;
            }

            if ($reservation->status === Reservation::STATUS_EXPIRED) {
                $reservation->pickup_branch_id = null;
                $reservation->ready_at ??= $reservation->reserved_at ?: now()->subDay();
                $reservation->expires_at ??= now()->subDay();
                $reservation->fulfilled_at = null;
                $reservation->cancelled_at = null;

                return;
            }

            if ($reservation->status === Reservation::STATUS_FULFILLED) {
                $reservation->fulfilled_at ??= now();
                $reservation->cancelled_at = null;

                return;
            }

            if ($reservation->status === Reservation::STATUS_CANCELLED) {
                $reservation->pickup_branch_id = null;
                $reservation->cancelled_at ??= now();
                $reservation->fulfilled_at = null;
            }
        });
    }

    public function definition(): array
    {
        $reservedAt = fake()->dateTimeBetween('-30 days', 'now');

        $status = fake()->randomElement([
            Reservation::STATUS_WAITING,
            Reservation::STATUS_READY,
            Reservation::STATUS_FULFILLED,
            Reservation::STATUS_CANCELLED,
            Reservation::STATUS_EXPIRED,
        ]);
        $readyAt = $status === Reservation::STATUS_READY
            ? fake()->dateTimeBetween($reservedAt, 'now')
            : null;

        return [
            'library_id' => Library::factory(),
            'book_id' => Book::factory(),
            'user_id' => User::factory(),
            'scope' => Reservation::SCOPE_LIBRARY,
            'branch_id' => null,
            'pickup_branch_id' => null,
            'assigned_book_copy_id' => null,
            'status' => $status,
            'reserved_at' => $reservedAt,
            'ready_at' => $readyAt,
            'expires_at' => $readyAt ? fake()->dateTimeBetween($readyAt, '+10 days') : null,
            'fulfilled_at' => null,
            'cancelled_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}


