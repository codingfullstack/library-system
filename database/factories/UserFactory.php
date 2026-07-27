<?php

namespace Database\Factories;

use App\Models\LibraryMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @var array<string, int>
     */
    protected static array $pendingLibraryIds = [];

    public function configure(): static
    {
        return $this
            ->afterMaking(function (User $user) {
                $attributes = $user->getAttributes();
                $hasLibraryId = array_key_exists('library_id', $attributes);
                $libraryId = $attributes['library_id'] ?? null;

                if ($hasLibraryId) {
                    unset($attributes['library_id']);
                    $user->setRawAttributes($attributes);
                }

                if ($libraryId) {
                    self::$pendingLibraryIds[$user->email] = (int) $libraryId;
                }
            })
            ->afterCreating(function (User $user) {
                $libraryId = self::$pendingLibraryIds[$user->email] ?? null;
                unset(self::$pendingLibraryIds[$user->email]);

                if (! $libraryId || $user->isSuperAdmin()) {
                    return;
                }

                $membership = LibraryMembership::query()->updateOrCreate(
                    [
                        'library_id' => $libraryId,
                        'user_id' => $user->id,
                    ],
                    [
                        'role' => $user->role,
                        'membership_number' => $user->membership_number,
                        'is_active' => $user->is_active,
                        'joined_at' => $user->created_at,
                    ]
                );

                if ($user->role === User::ROLE_STAFF && ! $membership->branch_id) {
                    $branch = \App\Models\Branch::query()
                        ->where('library_id', $libraryId)
                        ->orderBy('id')
                        ->first();

                    if ($branch) {
                        $membership->update(['branch_id' => $branch->id]);
                    }
                }
            });
    }

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'narys',
            'phone' => fake()->phoneNumber(),
            'membership_number' => fn () => 'MEM:' . (string) Str::ulid(),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role' => 'superadministratorius',
            'membership_number' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => 'administratorius',
        ]);
    }

    public function staff(): static
    {
        return $this->state(fn () => [
            'role' => 'darbuotojas',
        ]);
    }

    public function member(): static
    {
        return $this->state(fn () => [
            'role' => 'narys',
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code-1', 'code-2'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}


