<?php

namespace Database\Factories;

use App\Models\Library;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LibraryMembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'branch_id' => null,
            'user_id' => User::factory()->member(),
            'membership_number' => fn () => 'MEM:' . (string) Str::ulid(),
            'is_active' => true,
            'joined_at' => now(),
        ];
    }

    public function member(): static
    {
        return $this;
    }
}


