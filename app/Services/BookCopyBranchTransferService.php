<?php

namespace App\Services;

use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BookCopyBranchTransferService
{
    public const STAFF_TRANSFER_MESSAGE = 'Kopijos filialo keisti negalite.';

    public const READY_RESERVATION_TRANSFER_MESSAGE = 'Kopijos negalima perkelti, kol ji priskirta paruoštai rezervacijai.';

    public const CROSS_LIBRARY_MESSAGE = 'Kopijos negalima perkelti į kitos bibliotekos filialą.';

    public function libraryIdForCreate(User $actor, int|string|null $requestedLibraryId = null): int
    {
        $libraryId = $actor->isSuperAdmin()
            ? $requestedLibraryId
            : $actor->activeLibraryId();

        if (! $libraryId) {
            throw ValidationException::withMessages([
                'library_id' => 'Pasirinkite biblioteką.',
            ]);
        }

        return (int) $libraryId;
    }

    public function libraryIdForUpdate(User $actor, BookCopy $bookCopy, int|string|null $requestedLibraryId = null): int
    {
        if ($requestedLibraryId && (int) $requestedLibraryId !== (int) $bookCopy->library_id) {
            throw ValidationException::withMessages([
                'library_id' => 'Kopijos bibliotekos keisti negalima.',
            ]);
        }

        return (int) $bookCopy->library_id;
    }

    public function resolveBranchId(
        User $actor,
        int $libraryId,
        int|string|null $requestedBranchId,
        ?BookCopy $bookCopy = null,
        string $field = 'branch_id'
    ): int {
        if ($actor->role === User::ROLE_STAFF) {
            return $this->resolveStaffBranchId($actor, $libraryId, $requestedBranchId, $bookCopy, $field);
        }

        if (! $requestedBranchId) {
            throw ValidationException::withMessages([
                $field => 'Pasirinkite filialą.',
            ]);
        }

        $branch = Branch::query()->find((int) $requestedBranchId);

        if (! $branch || (int) $branch->library_id !== $libraryId) {
            throw ValidationException::withMessages([
                $field => self::CROSS_LIBRARY_MESSAGE,
            ]);
        }

        if ($bookCopy && (int) $bookCopy->library_id !== (int) $branch->library_id) {
            throw ValidationException::withMessages([
                $field => self::CROSS_LIBRARY_MESSAGE,
            ]);
        }

        if (
            $bookCopy
            && (int) $bookCopy->branch_id !== (int) $branch->id
            && $bookCopy->activeReadyReservation()->exists()
        ) {
            throw ValidationException::withMessages([
                $field => self::READY_RESERVATION_TRANSFER_MESSAGE,
            ]);
        }

        return (int) $branch->id;
    }

    public function canTransfer(User $actor, BookCopy $bookCopy, Branch|int|null $targetBranch = null): bool
    {
        if ($actor->role === User::ROLE_STAFF) {
            return false;
        }

        if (! $actor->canManageBookCopy($bookCopy)) {
            return false;
        }

        if (! $targetBranch) {
            return true;
        }

        $branch = $targetBranch instanceof Branch
            ? $targetBranch
            : Branch::query()->find($targetBranch);

        return $branch instanceof Branch
            && (int) $bookCopy->library_id === (int) $branch->library_id;
    }

    /**
     * @return Collection<int, Branch>
     */
    public function selectableBranches(User $actor, int|string|null $libraryId, ?BookCopy $bookCopy = null): Collection
    {
        $effectiveLibraryId = $bookCopy?->library_id ?: $libraryId;

        return Branch::query()
            ->when($effectiveLibraryId, fn (Builder $query) => $query->where('library_id', $effectiveLibraryId))
            ->when(! $actor->isSuperAdmin(), fn (Builder $query) => $query->where('library_id', $actor->activeLibraryId()))
            ->when($actor->role === User::ROLE_STAFF, function (Builder $query) use ($actor, $effectiveLibraryId) {
                $staffBranchId = $actor->assignedBranchId($effectiveLibraryId);

                $staffBranchId
                    ? $query->whereKey($staffBranchId)
                    : $query->whereRaw('1 = 0');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'library_id']);
    }

    private function resolveStaffBranchId(
        User $actor,
        int $libraryId,
        int|string|null $requestedBranchId,
        ?BookCopy $bookCopy,
        string $field
    ): int {
        $staffBranchId = $actor->assignedBranchId($libraryId);

        if (! $staffBranchId) {
            throw ValidationException::withMessages([
                $field => 'Darbuotojas turi būti priskirtas filialui.',
            ]);
        }

        if ($bookCopy && (int) $bookCopy->branch_id !== (int) $staffBranchId) {
            throw ValidationException::withMessages([
                $field => self::STAFF_TRANSFER_MESSAGE,
            ]);
        }

        if ($requestedBranchId && (int) $requestedBranchId !== (int) $staffBranchId) {
            throw ValidationException::withMessages([
                $field => $bookCopy ? self::STAFF_TRANSFER_MESSAGE : 'Darbuotojas gali pridėti kopiją tik savo filiale.',
            ]);
        }

        return (int) $staffBranchId;
    }
}
