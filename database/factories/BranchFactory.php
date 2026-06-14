<?php

namespace Database\Factories;

use App\Models\Library;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function ($branch) {
            \App\Models\LibraryMembership::query()
                ->where('library_id', $branch->library_id)
                ->whereNull('branch_id')
                ->whereHas('user', fn ($query) => $query->where('role', \App\Models\User::ROLE_STAFF))
                ->update(['branch_id' => $branch->id]);
        });
    }

    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'name' => fake()->company() . ' Branch',
            'code' => strtoupper(fake()->bothify('BR-###??')),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
        ];
    }
}

