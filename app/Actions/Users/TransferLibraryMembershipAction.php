<?php

namespace App\Actions\Users;

use App\Models\Branch;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferLibraryMembershipAction
{
    public function execute(
        User $actor,
        LibraryMembership $membership,
        int $targetLibraryId,
        ?int $targetBranchId,
    ): LibraryMembership {
        return DB::transaction(function () use ($actor, $membership, $targetLibraryId, $targetBranchId): LibraryMembership {
            $lockedMembership = LibraryMembership::query()
                ->whereKey($membership->id)
                ->lockForUpdate()
                ->firstOrFail();

            $user = $lockedMembership->user()->lockForUpdate()->firstOrFail();
            $sourceLibraryId = (int) $lockedMembership->library_id;

            $this->authorize($actor, $user, $sourceLibraryId, $targetLibraryId);
            $this->validateTarget($user, $lockedMembership, $targetLibraryId, $targetBranchId);

            $lockedMembership->fill([
                'library_id' => $targetLibraryId,
                'branch_id' => $user->role === User::ROLE_STAFF ? $targetBranchId : null,
                'membership_number' => $user->membership_number,
                'joined_at' => $lockedMembership->joined_at ?: $user->created_at,
            ]);

            if ($lockedMembership->isDirty()) {
                $lockedMembership->save();
            }

            return $lockedMembership->refresh();
        });
    }

    private function authorize(User $actor, User $target, int $sourceLibraryId, int $targetLibraryId): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if (! $actor->belongsToLibrary($sourceLibraryId) || ! $target->hasMembershipInLibrary($sourceLibraryId)) {
            throw ValidationException::withMessages([
                'libraryId' => 'Negalite valdyti šios bibliotekos narystės.',
            ]);
        }

        if ($sourceLibraryId !== $targetLibraryId) {
            throw ValidationException::withMessages([
                'libraryId' => 'Bibliotekos administratorius negali perkelti vartotojo į kitą biblioteką.',
            ]);
        }
    }

    private function validateTarget(
        User $user,
        LibraryMembership $membership,
        int $targetLibraryId,
        ?int $targetBranchId,
    ): void {
        $targetLibraryExists = Library::query()
            ->whereKey($targetLibraryId)
            ->where('is_active', true)
            ->exists();

        if (! $targetLibraryExists) {
            throw ValidationException::withMessages([
                'libraryId' => 'Pasirinkta biblioteka nerasta arba neaktyvi.',
            ]);
        }

        $conflictingMembershipExists = LibraryMembership::query()
            ->where('user_id', $membership->user_id)
            ->where('library_id', $targetLibraryId)
            ->whereKeyNot($membership->id)
            ->exists();

        if ($conflictingMembershipExists) {
            throw ValidationException::withMessages([
                'libraryId' => 'Vartotojas jau turi narystę pasirinktoje bibliotekoje.',
            ]);
        }

        if ($user->role === User::ROLE_STAFF && ! $targetBranchId) {
            throw ValidationException::withMessages([
                'branchId' => 'Darbuotojo narystei privalomas filialas.',
            ]);
        }

        if ($targetBranchId) {
            $branchBelongsToTargetLibrary = Branch::query()
                ->whereKey($targetBranchId)
                ->where('library_id', $targetLibraryId)
                ->exists();

            if (! $branchBelongsToTargetLibrary) {
                throw ValidationException::withMessages([
                    'branchId' => 'Filialas turi priklausyti pasirinktai bibliotekai.',
                ]);
            }
        }

        if ((int) $membership->library_id !== $targetLibraryId) {
            $hasHistoricalRows = Loan::query()
                ->where('user_id', $membership->user_id)
                ->where('library_id', $membership->library_id)
                ->exists()
                || Reservation::query()
                    ->where('user_id', $membership->user_id)
                    ->where('library_id', $membership->library_id)
                    ->exists();

            if ($hasHistoricalRows) {
                throw ValidationException::withMessages([
                    'libraryId' => 'Narystės negalima perkelti, nes senojoje bibliotekoje yra vartotojo paskolų arba rezervacijų istorija.',
                ]);
            }
        }
    }
}
