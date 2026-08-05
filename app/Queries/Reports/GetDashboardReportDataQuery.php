<?php

namespace App\Queries\Reports;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Publisher;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetDashboardReportDataQuery
{
    /**
     * @return array<string, int>
     */
    public function summary(User $user, array $filters = []): array
    {
        $libraryId = $user->isSuperAdmin() ? null : $user->activeLibraryId();

        if (! $user->isSuperAdmin() && ! $libraryId) {
            return $this->emptySummary();
        }

        $effectiveRole = $libraryId ? $user->effectiveRole($libraryId) : $user->role;
        $staffBranchId = $effectiveRole === User::ROLE_STAFF ? ($user->assignedBranchId($libraryId) ?? 0) : null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        $copiesQuery = BookCopy::query()
            ->when($libraryId, fn (Builder $query) => $query->where('library_id', $libraryId));
        $this->applyStaffBookCopyScope($copiesQuery, $staffBranchId);
        $this->applyBookCopyPeriod($copiesQuery, $dateFrom, $dateTo);

        $loansQuery = Loan::query()
            ->when($libraryId, fn (Builder $query) => $query->where('library_id', $libraryId));
        $this->applyStaffLoanScope($loansQuery, $staffBranchId);
        $this->applyLoanPeriod($loansQuery, $dateFrom, $dateTo);

        $reservationsQuery = Reservation::query()
            ->when($libraryId, fn (Builder $query) => $query->where('library_id', $libraryId));
        $this->applyStaffReservationScope($reservationsQuery, $staffBranchId);
        $this->applyReservationPeriod($reservationsQuery, $dateFrom, $dateTo);

        $membersQuery = User::query()
            ->where('is_active', true)
            ->when(
                $libraryId,
                fn (Builder $query) => $query->whereHas('libraryMemberships', fn (Builder $membershipQuery) => $membershipQuery
                    ->where('library_id', $libraryId)
                    ->where('is_active', true)),
                fn (Builder $query) => $query->where('role', User::ROLE_MEMBER)
            );
        $this->applyStaffMemberScope($membersQuery, $libraryId, $staffBranchId);

        return [
            'book_copies_count' => (clone $copiesQuery)->count(),
            'available_book_copies_count' => (clone $copiesQuery)->where('status', BookCopy::STATUS_AVAILABLE)->count(),
            'active_loans_count' => (clone $loansQuery)->active()->count(),
            'returned_loans_count' => (clone $loansQuery)->whereNotNull('returned_at')->count(),
            'active_reservations_count' => (clone $reservationsQuery)->active()->count(),
            'fulfilled_reservations_count' => (clone $reservationsQuery)->where('status', Reservation::STATUS_FULFILLED)->count(),
            'overdue_loans_count' => (clone $loansQuery)
                ->whereNull('returned_at')
                ->where(function (Builder $query) {
                    $query->where('status', Loan::STATUS_OVERDUE)
                        ->orWhere(function (Builder $dateQuery) {
                            $dateQuery->whereNotNull('due_at')
                                ->where('due_at', '<', now());
                        });
                })
                ->count(),
            'lost_book_copies_count' => (clone $copiesQuery)->where('status', BookCopy::STATUS_LOST)->count(),
            'damaged_book_copies_count' => (clone $copiesQuery)->where('condition_status', BookCopy::CONDITION_DAMAGED)->count(),
            'maintenance_book_copies_count' => (clone $copiesQuery)->where('status', BookCopy::STATUS_MAINTENANCE)->count(),
            'withdrawn_book_copies_count' => (clone $copiesQuery)->where('status', BookCopy::STATUS_WITHDRAWN)->count(),
            'active_members_count' => (clone $membersQuery)->count(),
            'loans_count' => (clone $loansQuery)->count(),
            'reservations_count' => (clone $reservationsQuery)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptySummary(): array
    {
        return [
            'book_copies_count' => 0,
            'available_book_copies_count' => 0,
            'active_loans_count' => 0,
            'returned_loans_count' => 0,
            'active_reservations_count' => 0,
            'fulfilled_reservations_count' => 0,
            'overdue_loans_count' => 0,
            'lost_book_copies_count' => 0,
            'damaged_book_copies_count' => 0,
            'maintenance_book_copies_count' => 0,
            'withdrawn_book_copies_count' => 0,
            'active_members_count' => 0,
            'loans_count' => 0,
            'reservations_count' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters = []): array
    {
        $libraryId = $user->isSuperAdmin() ? null : $user->activeLibraryId();
        $effectiveRole = $libraryId ? $user->effectiveRole($libraryId) : $user->role;
        $staffBranchId = $effectiveRole === User::ROLE_STAFF ? ($user->assignedBranchId($libraryId) ?? 0) : null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $periodLabel = $filters['period_label'] ?? 'Visas laikotarpis';

        $copiesQuery = BookCopy::query()
            ->when($libraryId, fn (Builder $query) => $query->where('library_id', $libraryId));
        $this->applyStaffBookCopyScope($copiesQuery, $staffBranchId);
        $this->applyBookCopyPeriod($copiesQuery, $dateFrom, $dateTo);

        $loansQuery = Loan::query()
            ->when($libraryId, fn (Builder $query) => $query->where('library_id', $libraryId));
        $this->applyStaffLoanScope($loansQuery, $staffBranchId);
        $this->applyLoanPeriod($loansQuery, $dateFrom, $dateTo);

        $reservationsQuery = Reservation::query()
            ->when($libraryId, fn (Builder $query) => $query->where('library_id', $libraryId));
        $this->applyStaffReservationScope($reservationsQuery, $staffBranchId);
        $this->applyReservationPeriod($reservationsQuery, $dateFrom, $dateTo);

        $membersQuery = User::query()
            ->where('is_active', true)
            ->when(
                $libraryId,
                fn (Builder $query) => $query->whereHas('libraryMemberships', fn (Builder $membershipQuery) => $membershipQuery
                    ->where('library_id', $libraryId)
                    ->where('is_active', true)),
                fn (Builder $query) => $query->where('role', 'narys')
            );
        $this->applyStaffMemberScope($membersQuery, $libraryId, $staffBranchId);

        $summary = $this->summary($user, $filters);

        $libraryComparison = $this->getLibraryComparison($libraryId, $dateFrom, $dateTo);
        $popularBooks = $this->getPopularBooks($libraryId, $dateFrom, $dateTo);
        $popularAuthors = $this->getPopularAuthors($libraryId, $dateFrom, $dateTo);
        $popularCategories = $this->getPopularCategories($libraryId, $dateFrom, $dateTo);
        $popularPublishers = $this->getPopularPublishers($libraryId, $dateFrom, $dateTo);
        $popularBookCopies = $this->getPopularBookCopies($libraryId, $dateFrom, $dateTo);
        $activeMembers = $this->getActiveMembers($libraryId, $dateFrom, $dateTo, $staffBranchId);
        $copiesByStatus = $this->getCopiesByStatus($copiesQuery);
        $copiesByBranch = $this->getCopiesByBranch($libraryId, $dateFrom, $dateTo);
        $activityTimeline = $this->getActivityTimeline($libraryId, $dateFrom, $dateTo);

        return [
            'summary' => $summary,
            'libraryComparison' => $libraryComparison,
            'popularBooks' => $popularBooks,
            'popularAuthors' => $popularAuthors,
            'popularCategories' => $popularCategories,
            'popularPublishers' => $popularPublishers,
            'popularBookCopies' => $popularBookCopies,
            'activeMembers' => $activeMembers,
            'copiesByStatus' => $copiesByStatus,
            'copiesByBranch' => $copiesByBranch,
            'activityTimeline' => $activityTimeline,
            'periodLabel' => $periodLabel,
            'scopeLabel' => $user->isSuperAdmin()
                ? 'Visų bibliotekų statistika - '.$periodLabel
                : ($user->availableLibraries()->firstWhere('id', $libraryId)?->name
                    ? $user->availableLibraries()->firstWhere('id', $libraryId)->name.' statistika - '.$periodLabel
                    : 'Bibliotekos statistika - '.$periodLabel),
        ];
    }

    protected function getLibraryComparison(?int $libraryId, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        return Library::query()
            ->when($libraryId, fn (Builder $query) => $query->whereKey($libraryId))
            ->when(! $libraryId, function (Builder $query) {
                $query->where(function (Builder $libraryQuery) {
                    $libraryQuery->has('bookCopies')
                        ->orHas('loans')
                        ->orHas('reservations')
                        ->orHas('memberships');
                });
            })
            ->withCount([
                'bookCopies' => fn (Builder $query) => $this->applyBookCopyPeriod($query, $dateFrom, $dateTo),
                'bookCopies as available_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod(
                    $query->where('status', BookCopy::STATUS_AVAILABLE),
                    $dateFrom,
                    $dateTo
                ),
                'bookCopies as lost_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod(
                    $query->where('status', BookCopy::STATUS_LOST),
                    $dateFrom,
                    $dateTo
                ),
                'bookCopies as damaged_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod(
                    $query->where('condition_status', BookCopy::CONDITION_DAMAGED),
                    $dateFrom,
                    $dateTo
                ),
                'bookCopies as maintenance_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod(
                    $query->where('status', BookCopy::STATUS_MAINTENANCE),
                    $dateFrom,
                    $dateTo
                ),
                'bookCopies as withdrawn_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod(
                    $query->where('status', BookCopy::STATUS_WITHDRAWN),
                    $dateFrom,
                    $dateTo
                ),
                'loans' => fn (Builder $query) => $this->applyLoanPeriod($query, $dateFrom, $dateTo),
                'loans as active_loans_count' => function (Builder $query) use ($dateFrom, $dateTo) {
                    $this->applyLoanPeriod($query, $dateFrom, $dateTo);

                    $query->whereNull('returned_at')
                        ->whereIn('status', ['aktyvi', 'vėluoja']);
                },
                'loans as overdue_loans_count' => function (Builder $query) use ($dateFrom, $dateTo) {
                    $this->applyLoanPeriod($query, $dateFrom, $dateTo);

                    $query->whereNull('returned_at')
                        ->where(function (Builder $overdueQuery) {
                            $overdueQuery->where('status', 'vėluoja')
                                ->orWhere(function (Builder $dateQuery) {
                                    $dateQuery->whereNotNull('due_at')
                                        ->where('due_at', '<', now());
                                });
                        });
                },
                'reservations' => fn (Builder $query) => $this->applyReservationPeriod($query, $dateFrom, $dateTo),
                'reservations as active_reservations_count' => function (Builder $query) use ($dateFrom, $dateTo) {
                    $this->applyReservationPeriod($query, $dateFrom, $dateTo);

                    $query->active();
                },
                'reservations as fulfilled_reservations_count' => function (Builder $query) use ($dateFrom, $dateTo) {
                    $this->applyReservationPeriod($query, $dateFrom, $dateTo);

                    $query->where('status', Reservation::STATUS_FULFILLED);
                },
                'users as active_members_count' => function (Builder $query) {
                    $query->where('users.role', 'narys')
                        ->where('users.is_active', true)
                        ->where('library_memberships.is_active', true);
                },
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    protected function getPopularBooks(?int $libraryId, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        return Book::query()
            ->select(['books.id', 'books.title', 'books.isbn'])
            ->with('authors:id,name')
            ->when($libraryId, function (Builder $query) use ($libraryId) {
                $query->whereHas('bookCopies', fn (Builder $copyQuery) => $copyQuery->where('book_copies.library_id', $libraryId));
            })
            ->where(function (Builder $query) use ($libraryId, $dateFrom, $dateTo) {
                $query->whereHas('loans', function (Builder $loanQuery) use ($libraryId, $dateFrom, $dateTo) {
                    $this->applyLoanPeriod($loanQuery, $dateFrom, $dateTo);

                    if ($libraryId) {
                        $loanQuery->where('loans.library_id', $libraryId);
                    }
                })->orWhereHas('reservations', function (Builder $reservationQuery) use ($libraryId, $dateFrom, $dateTo) {
                    $this->applyReservationPeriod($reservationQuery, $dateFrom, $dateTo);

                    if ($libraryId) {
                        $reservationQuery->where('reservations.library_id', $libraryId);
                    }
                });
            })
            ->withCount([
                'loans as loans_count' => function (Builder $query) use ($libraryId, $dateFrom, $dateTo) {
                    $this->applyLoanPeriod($query, $dateFrom, $dateTo);

                    if ($libraryId) {
                        $query->where('loans.library_id', $libraryId);
                    }
                },
                'reservations as reservations_count' => function (Builder $query) use ($libraryId, $dateFrom, $dateTo) {
                    $this->applyReservationPeriod($query, $dateFrom, $dateTo);

                    if ($libraryId) {
                        $query->where('reservations.library_id', $libraryId);
                    }
                },
            ])
            ->orderByDesc('loans_count')
            ->orderByDesc('reservations_count')
            ->orderBy('title')
            ->limit(7)
            ->get();
    }

    protected function getPopularAuthors(?int $libraryId, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        $loanActivity = $this->loanActivityByBookSubquery($libraryId, $dateFrom, $dateTo);
        $reservationActivity = $this->reservationActivityByBookSubquery($libraryId, $dateFrom, $dateTo);

        return Author::query()
            ->select([
                'authors.id',
                'authors.name',
                DB::raw('COUNT(DISTINCT books.id) as books_count'),
                DB::raw('COALESCE(SUM(loan_activity.loans_count), 0) as loans_count'),
                DB::raw('COALESCE(SUM(reservation_activity.reservations_count), 0) as reservations_count'),
            ])
            ->join('book_author', 'book_author.author_id', '=', 'authors.id')
            ->join('books', 'books.id', '=', 'book_author.book_id')
            ->leftJoinSub($loanActivity, 'loan_activity', fn ($join) => $join->on('loan_activity.book_id', '=', 'books.id'))
            ->leftJoinSub($reservationActivity, 'reservation_activity', fn ($join) => $join->on('reservation_activity.book_id', '=', 'books.id'))
            ->groupBy('authors.id', 'authors.name')
            ->havingRaw('COALESCE(SUM(loan_activity.loans_count), 0) + COALESCE(SUM(reservation_activity.reservations_count), 0) > 0')
            ->orderByDesc('loans_count')
            ->orderByDesc('reservations_count')
            ->orderBy('authors.name')
            ->limit(7)
            ->get();
    }

    protected function getPopularCategories(?int $libraryId, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        $loanActivity = $this->loanActivityByBookSubquery($libraryId, $dateFrom, $dateTo);
        $reservationActivity = $this->reservationActivityByBookSubquery($libraryId, $dateFrom, $dateTo);

        return Category::query()
            ->select([
                'categories.id',
                'categories.name',
                DB::raw('COUNT(DISTINCT books.id) as books_count'),
                DB::raw('COALESCE(SUM(loan_activity.loans_count), 0) as loans_count'),
                DB::raw('COALESCE(SUM(reservation_activity.reservations_count), 0) as reservations_count'),
            ])
            ->join('book_category', 'book_category.category_id', '=', 'categories.id')
            ->join('books', 'books.id', '=', 'book_category.book_id')
            ->leftJoinSub($loanActivity, 'loan_activity', fn ($join) => $join->on('loan_activity.book_id', '=', 'books.id'))
            ->leftJoinSub($reservationActivity, 'reservation_activity', fn ($join) => $join->on('reservation_activity.book_id', '=', 'books.id'))
            ->groupBy('categories.id', 'categories.name')
            ->havingRaw('COALESCE(SUM(loan_activity.loans_count), 0) + COALESCE(SUM(reservation_activity.reservations_count), 0) > 0')
            ->orderByDesc('loans_count')
            ->orderByDesc('reservations_count')
            ->orderBy('categories.name')
            ->limit(7)
            ->get();
    }

    protected function getPopularPublishers(?int $libraryId, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        $loanActivity = $this->loanActivityByBookSubquery($libraryId, $dateFrom, $dateTo);
        $reservationActivity = $this->reservationActivityByBookSubquery($libraryId, $dateFrom, $dateTo);

        return Publisher::query()
            ->select([
                'publishers.id',
                'publishers.name',
                DB::raw('COUNT(DISTINCT books.id) as books_count'),
                DB::raw('COALESCE(SUM(loan_activity.loans_count), 0) as loans_count'),
                DB::raw('COALESCE(SUM(reservation_activity.reservations_count), 0) as reservations_count'),
            ])
            ->join('books', 'books.publisher_id', '=', 'publishers.id')
            ->leftJoinSub($loanActivity, 'loan_activity', fn ($join) => $join->on('loan_activity.book_id', '=', 'books.id'))
            ->leftJoinSub($reservationActivity, 'reservation_activity', fn ($join) => $join->on('reservation_activity.book_id', '=', 'books.id'))
            ->groupBy('publishers.id', 'publishers.name')
            ->havingRaw('COALESCE(SUM(loan_activity.loans_count), 0) + COALESCE(SUM(reservation_activity.reservations_count), 0) > 0')
            ->orderByDesc('loans_count')
            ->orderByDesc('reservations_count')
            ->orderBy('publishers.name')
            ->limit(7)
            ->get();
    }

    protected function getPopularBookCopies(?int $libraryId, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        return BookCopy::query()
            ->select([
                'book_copies.id',
                'book_copies.book_id',
                'book_copies.library_id',
                'book_copies.branch_id',
                'book_copies.inventory_code',
                'book_copies.status',
            ])
            ->with([
                'book:id,slug,title',
                'library:id,name',
                'branch:id,name',
            ])
            ->when($libraryId, fn (Builder $query) => $query->where('book_copies.library_id', $libraryId))
            ->whereHas('loans', function (Builder $query) use ($dateFrom, $dateTo, $libraryId) {
                $this->applyLoanPeriod($query, $dateFrom, $dateTo);

                if ($libraryId) {
                    $query->where('library_id', $libraryId);
                }
            })
            ->withCount([
                'loans as loans_count' => function (Builder $query) use ($dateFrom, $dateTo, $libraryId) {
                    $this->applyLoanPeriod($query, $dateFrom, $dateTo);

                    if ($libraryId) {
                        $query->where('library_id', $libraryId);
                    }
                },
            ])
            ->orderByDesc('loans_count')
            ->orderBy('inventory_code')
            ->limit(7)
            ->get();
    }

    protected function getActiveMembers(?int $libraryId, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo, ?int $staffBranchId = null): Collection
    {
        return User::query()
            ->select(['users.id', 'users.name', 'users.membership_number'])
            ->when(
                $libraryId,
                fn (Builder $query) => $query->whereHas('libraryMemberships', fn (Builder $membershipQuery) => $membershipQuery
                    ->where('library_id', $libraryId)
                    ->where('is_active', true)),
                fn (Builder $query) => $query->where('role', 'narys')
            )
            ->tap(fn (Builder $query) => $this->applyStaffMemberScope($query, $libraryId, $staffBranchId))
            ->where(function (Builder $query) use ($libraryId, $dateFrom, $dateTo) {
                $query
                    ->whereHas('loans', function (Builder $loanQuery) use ($libraryId, $dateFrom, $dateTo) {
                        $this->applyLoanPeriod($loanQuery, $dateFrom, $dateTo);

                        if ($libraryId) {
                            $loanQuery->where('library_id', $libraryId);
                        }
                    })
                    ->orWhereHas('reservations', function (Builder $reservationQuery) use ($libraryId, $dateFrom, $dateTo) {
                        $this->applyReservationPeriod($reservationQuery, $dateFrom, $dateTo);

                        if ($libraryId) {
                            $reservationQuery->where('library_id', $libraryId);
                        }
                    });
            })
            ->with([
                'libraryMemberships' => fn ($query) => $query
                    ->when($libraryId, fn ($membershipQuery) => $membershipQuery->where('library_id', $libraryId))
                    ->when($libraryId, fn ($membershipQuery) => $membershipQuery->where('is_active', true))
                    ->with('library:id,name,code'),
            ])
            ->withCount([
                'loans as loans_count' => function (Builder $query) use ($libraryId, $dateFrom, $dateTo) {
                    $this->applyLoanPeriod($query, $dateFrom, $dateTo);

                    if ($libraryId) {
                        $query->where('library_id', $libraryId);
                    }
                },
                'reservations as reservations_count' => function (Builder $query) use ($libraryId, $dateFrom, $dateTo) {
                    $this->applyReservationPeriod($query, $dateFrom, $dateTo);

                    if ($libraryId) {
                        $query->where('library_id', $libraryId);
                    }
                },
            ])
            ->orderByRaw('(loans_count + reservations_count) desc')
            ->orderByDesc('loans_count')
            ->orderBy('users.name')
            ->limit(7)
            ->get()
            ->map(function (User $member) {
                $member->activity_points = (int) $member->loans_count + (int) $member->reservations_count;

                return $member;
            });
    }

    protected function getCopiesByStatus(Builder $copiesQuery): Collection
    {
        $counts = (clone $copiesQuery)
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(BookCopy::statusLabels())
            ->map(function (string $label, string $status) use ($counts) {
                return (object) [
                    'status' => $status,
                    'label' => $label,
                    'count' => (int) ($counts[$status] ?? 0),
                ];
            })
            ->values();
    }

    protected function getCopiesByBranch(?int $libraryId, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        $reservationCountSubquery = Reservation::query()
            ->selectRaw('COUNT(DISTINCT reservations.id)')
            ->join('book_copies', 'book_copies.book_id', '=', 'reservations.book_id')
            ->whereColumn('book_copies.branch_id', 'branches.id')
            ->active();

        $this->applyReservationPeriod($reservationCountSubquery, $dateFrom, $dateTo);

        return Branch::query()
            ->select(['branches.id', 'branches.library_id', 'branches.name', 'branches.code'])
            ->selectSub($reservationCountSubquery, 'active_reservations_count')
            ->with('library:id,name')
            ->when($libraryId, fn (Builder $query) => $query->where('branches.library_id', $libraryId))
            ->when(! $libraryId, fn (Builder $query) => $query->whereHas('bookCopies'))
            ->withCount([
                'bookCopies' => fn (Builder $query) => $this->applyBookCopyPeriod($query, $dateFrom, $dateTo),
                'bookCopies as available_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod(
                    $query->where('status', BookCopy::STATUS_AVAILABLE),
                    $dateFrom,
                    $dateTo
                ),
                'bookCopies as loaned_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod(
                    $query->where('status', BookCopy::STATUS_LOANED),
                    $dateFrom,
                    $dateTo
                ),
                'bookCopies as lost_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod(
                    $query->where('status', BookCopy::STATUS_LOST),
                    $dateFrom,
                    $dateTo
                ),
                'bookCopies as damaged_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod(
                    $query->where('condition_status', BookCopy::CONDITION_DAMAGED),
                    $dateFrom,
                    $dateTo
                ),
                'bookCopies as maintenance_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod(
                    $query->where('status', BookCopy::STATUS_MAINTENANCE),
                    $dateFrom,
                    $dateTo
                ),
                'bookCopies as withdrawn_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod(
                    $query->where('status', BookCopy::STATUS_WITHDRAWN),
                    $dateFrom,
                    $dateTo
                ),
            ])
            ->orderBy('branches.name')
            ->limit(12)
            ->get();
    }

    protected function getActivityTimeline(?int $libraryId, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        if (! $dateFrom || ! $dateTo) {
            $dateFrom = now()->toImmutable()->subMonths(11)->startOfMonth();
            $dateTo = now()->toImmutable()->endOfMonth();
        }

        $groupByMonth = $dateFrom->diffInDays($dateTo) > 62;
        $periodStep = $groupByMonth ? '1 month' : '1 day';
        $period = CarbonPeriod::create($dateFrom, $periodStep, $dateTo);

        $issued = Loan::query()
            ->when($libraryId, fn (Builder $query) => $query->where('library_id', $libraryId))
            ->whereBetween('borrowed_at', [$dateFrom, $dateTo])
            ->selectRaw($this->dateBucketSelect('borrowed_at', $groupByMonth).', COUNT(*) as aggregate')
            ->groupBy('period_key')
            ->pluck('aggregate', 'period_key');

        $returned = Loan::query()
            ->when($libraryId, fn (Builder $query) => $query->where('library_id', $libraryId))
            ->whereNotNull('returned_at')
            ->whereBetween('returned_at', [$dateFrom, $dateTo])
            ->selectRaw($this->dateBucketSelect('returned_at', $groupByMonth).', COUNT(*) as aggregate')
            ->groupBy('period_key')
            ->pluck('aggregate', 'period_key');

        $reserved = Reservation::query()
            ->when($libraryId, fn (Builder $query) => $query->where('library_id', $libraryId))
            ->whereBetween('reserved_at', [$dateFrom, $dateTo])
            ->selectRaw($this->dateBucketSelect('reserved_at', $groupByMonth).', COUNT(*) as aggregate')
            ->groupBy('period_key')
            ->pluck('aggregate', 'period_key');

        return collect($period)
            ->map(function ($date) use ($groupByMonth, $issued, $returned, $reserved) {
                $key = $groupByMonth ? $date->format('Y-m-01') : $date->format('Y-m-d');

                return (object) [
                    'label' => $groupByMonth ? $date->translatedFormat('Y m.') : $date->format('Y-m-d'),
                    'issued_loans_count' => (int) ($issued[$key] ?? 0),
                    'returned_loans_count' => (int) ($returned[$key] ?? 0),
                    'reservations_count' => (int) ($reserved[$key] ?? 0),
                ];
            })
            ->filter(fn (object $row) => $row->issued_loans_count > 0 || $row->returned_loans_count > 0 || $row->reservations_count > 0)
            ->values();
    }

    protected function dateBucketSelect(string $column, bool $groupByMonth): string
    {
        $qualifiedColumn = DB::connection()->getQueryGrammar()->wrap($column);
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $format = $groupByMonth ? '%Y-%m-01' : '%Y-%m-%d';

            return "strftime('{$format}', {$qualifiedColumn}) as period_key";
        }

        $format = $groupByMonth ? '%Y-%m-01' : '%Y-%m-%d';

        return "DATE_FORMAT({$qualifiedColumn}, '{$format}') as period_key";
    }

    protected function loanActivityByBookSubquery(?int $libraryId, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Builder
    {
        $query = Loan::query()
            ->selectRaw('book_copies.book_id as book_id, COUNT(*) as loans_count')
            ->join('book_copies', 'book_copies.id', '=', 'loans.book_copy_id')
            ->when($libraryId, fn (Builder $builder) => $builder->where('loans.library_id', $libraryId));

        $this->applyLoanPeriod($query, $dateFrom, $dateTo);

        return $query->groupBy('book_copies.book_id');
    }

    protected function reservationActivityByBookSubquery(?int $libraryId, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Builder
    {
        $query = Reservation::query()
            ->selectRaw('book_id, COUNT(*) as reservations_count')
            ->when($libraryId, fn (Builder $builder) => $builder->where('library_id', $libraryId));

        $this->applyReservationPeriod($query, $dateFrom, $dateTo);

        return $query->groupBy('book_id');
    }

    protected function applyBookCopyPeriod(Builder $query, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Builder
    {
        if (! $dateFrom || ! $dateTo) {
            return $query;
        }

        return $query->where(function (Builder $copyQuery) use ($dateFrom, $dateTo, $query) {
            $copyQuery
                ->whereBetween($query->getModel()->qualifyColumn('created_at'), [$dateFrom, $dateTo])
                ->orWhereHas('loans', function (Builder $loanQuery) use ($dateFrom, $dateTo) {
                    $loanQuery->where(function (Builder $activityQuery) use ($dateFrom, $dateTo) {
                        $activityQuery
                            ->whereBetween('borrowed_at', [$dateFrom, $dateTo])
                            ->orWhere(function (Builder $returnedQuery) use ($dateFrom, $dateTo) {
                                $returnedQuery
                                    ->whereNotNull('returned_at')
                                    ->whereBetween('returned_at', [$dateFrom, $dateTo]);
                            });
                    });
                })
                ->orWhereHas('statusHistories', function (Builder $historyQuery) use ($dateFrom, $dateTo) {
                    $historyQuery->whereBetween('changed_at', [$dateFrom, $dateTo]);
                });
        });
    }

    protected function applyLoanPeriod(Builder $query, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Builder
    {
        if (! $dateFrom || ! $dateTo) {
            return $query;
        }

        return $query->whereBetween('borrowed_at', [$dateFrom, $dateTo]);
    }

    protected function applyStaffBookCopyScope(Builder $query, ?int $staffBranchId): Builder
    {
        if ($staffBranchId === null) {
            return $query;
        }

        if ($staffBranchId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('branch_id', $staffBranchId);
    }

    protected function applyReservationPeriod(Builder $query, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Builder
    {
        if (! $dateFrom || ! $dateTo) {
            return $query;
        }

        return $query->whereBetween('reserved_at', [$dateFrom, $dateTo]);
    }

    protected function applyStaffLoanScope(Builder $query, ?int $staffBranchId): Builder
    {
        if ($staffBranchId === null) {
            return $query;
        }

        if ($staffBranchId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('bookCopy', fn (Builder $copyQuery) => $copyQuery->where('branch_id', $staffBranchId));
    }

    protected function applyStaffReservationScope(Builder $query, ?int $staffBranchId): Builder
    {
        if ($staffBranchId === null) {
            return $query;
        }

        if ($staffBranchId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scopeQuery) use ($staffBranchId) {
            $scopeQuery
                ->where(function (Builder $libraryScopeQuery) {
                    $libraryScopeQuery
                        ->where('scope', Reservation::SCOPE_LIBRARY)
                        ->whereNull('branch_id');
                })
                ->orWhere(function (Builder $branchScopeQuery) use ($staffBranchId) {
                    $branchScopeQuery
                        ->where('scope', Reservation::SCOPE_BRANCH)
                        ->where('branch_id', $staffBranchId);
                });
        });
    }

    protected function applyStaffMemberScope(Builder $query, ?int $libraryId, ?int $staffBranchId): Builder
    {
        if ($staffBranchId === null || $libraryId === null) {
            return $query;
        }

        if ($staffBranchId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $activityQuery) use ($libraryId, $staffBranchId) {
            $activityQuery
                ->whereHas('loans.bookCopy', fn (Builder $copyQuery) => $copyQuery
                    ->where('library_id', $libraryId)
                    ->where('branch_id', $staffBranchId))
                ->orWhereHas('reservations', function (Builder $reservationQuery) use ($libraryId, $staffBranchId) {
                    $reservationQuery
                        ->where('library_id', $libraryId)
                        ->where(function (Builder $scopeQuery) use ($staffBranchId) {
                            $scopeQuery
                                ->where(function (Builder $libraryScopeQuery) {
                                    $libraryScopeQuery
                                        ->where('scope', Reservation::SCOPE_LIBRARY)
                                        ->whereNull('branch_id');
                                })
                                ->orWhere(function (Builder $branchScopeQuery) use ($staffBranchId) {
                                    $branchScopeQuery
                                        ->where('scope', Reservation::SCOPE_BRANCH)
                                        ->where('branch_id', $staffBranchId);
                                });
                        });
                });
        });
    }
}
