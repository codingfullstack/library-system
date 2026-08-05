<?php

namespace App\Support;

use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class LibraryJoinService
{
    public const STATUS_NONE = 'none';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const INACTIVE_MESSAGE = 'Jūsų narystė šioje bibliotekoje yra deaktyvuota. Dėl atkūrimo kreipkitės į bibliotekos administratorių.';

    /**
     * @return array{membership_status: string, can_join: bool}
     */
    public function statusFor(User $user, Library $library): array
    {
        $membership = $this->existingMembership($user, $library);

        if (! $membership) {
            return [
                'membership_status' => self::STATUS_NONE,
                'can_join' => true,
            ];
        }

        if ($membership->is_active) {
            return [
                'membership_status' => self::STATUS_ACTIVE,
                'can_join' => false,
            ];
        }

        return [
            'membership_status' => self::STATUS_INACTIVE,
            'can_join' => false,
        ];
    }

    public function existingMembership(User $user, Library $library): ?LibraryMembership
    {
        return LibraryMembership::query()
            ->where('library_id', $library->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function join(User $user, Library $library): LibraryJoinResult
    {
        return DB::transaction(function () use ($user, $library): LibraryJoinResult {
            $membership = LibraryMembership::query()
                ->where('library_id', $library->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($membership?->is_active) {
                return LibraryJoinResult::active($membership);
            }

            if ($membership && ! $membership->is_active) {
                return LibraryJoinResult::inactive($membership);
            }

            try {
                $membership = LibraryMembership::query()->create([
                    'library_id' => $library->id,
                    'user_id' => $user->id,
                    'membership_number' => $user->membership_number ?: UserManagement::generateMembershipNumber(),
                    'is_active' => true,
                    'joined_at' => now(),
                ]);
            } catch (QueryException $exception) {
                $membership = $this->existingMembership($user, $library);

                if ($membership && ! $membership->is_active) {
                    return LibraryJoinResult::inactive($membership);
                }

                if ($membership) {
                    return LibraryJoinResult::active($membership);
                }

                throw $exception;
            }

            return LibraryJoinResult::created($membership);
        });
    }
}
