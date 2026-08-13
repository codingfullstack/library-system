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
use App\Support\Reports\DashboardScope;
use App\Support\Reports\DashboardScopeResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetDashboardReportDataQuery
{
    public function __construct(private readonly DashboardScopeResolver $scopeResolver) {}

    /**
     * @return array<string, int|null>
     */
    public function summary(User $user, array $filters = [], ?DashboardScope $scope = null): array
    {
        $scope ??= $this->scopeResolver->resolve($user, $filters['branch_id'] ?? null);
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        $copiesQuery = $this->applyBookCopyScope(BookCopy::query(), $scope);
        $this->applyBookCopyPeriod($copiesQuery, $dateFrom, $dateTo);

        $loansQuery = $this->applyLoanScope(Loan::query(), $scope);
        $this->applyLoanPeriod($loansQuery, $dateFrom, $dateTo);

        $returnedLoansQuery = $this->applyLoanScope(Loan::query(), $scope, 'returned');
        if ($dateFrom && $dateTo) {
            $returnedLoansQuery->whereBetween('returned_at', [$dateFrom, $dateTo]);
        }

        $reservationsQuery = $this->applyReservationScope(Reservation::query(), $scope);
        $this->applyReservationPeriod($reservationsQuery, $dateFrom, $dateTo);

        $todayLoans = $this->applyLoanScope(Loan::query(), $scope);
        $todayReturnedLoans = $this->applyLoanScope(Loan::query(), $scope, 'returned');
        $newMembers = $scope->isBranch() ? null : $this->newMembersCount($scope, $dateFrom, $dateTo);

        return [
            'book_copies_count' => (clone $copiesQuery)->count(),
            'available_book_copies_count' => (clone $copiesQuery)->operationallyAvailable()->count(),
            'active_loans_count' => (clone $loansQuery)->active()->count(),
            'returned_loans_count' => (clone $returnedLoansQuery)->whereNotNull('returned_at')->count(),
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
            'lost_book_copies_count' => (clone $copiesQuery)->where('lifecycle_status', BookCopy::STATUS_LOST)->count(),
            'damaged_book_copies_count' => 0,
            'maintenance_book_copies_count' => (clone $copiesQuery)->where('lifecycle_status', BookCopy::STATUS_MAINTENANCE)->count(),
            'withdrawn_book_copies_count' => (clone $copiesQuery)->where('lifecycle_status', BookCopy::STATUS_WITHDRAWN)->count(),
            'active_members_count' => $scope->isBranch() ? null : $this->activeMembersQuery($scope)->count(),
            'new_members_count' => $newMembers,
            'today_issued_count' => (clone $todayLoans)
                ->whereBetween('borrowed_at', [now()->startOfDay(), now()->endOfDay()])
                ->count(),
            'today_returned_count' => (clone $todayReturnedLoans)
                ->whereNotNull('returned_at')
                ->whereBetween('returned_at', [now()->startOfDay(), now()->endOfDay()])
                ->count(),
            'loans_count' => (clone $loansQuery)->count(),
            'reservations_count' => (clone $reservationsQuery)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters = []): array
    {
        $scope = $this->scopeResolver->resolve($user, $filters['branch_id'] ?? null);
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $periodLabel = $filters['period_label'] ?? 'Visas laikotarpis';

        $copiesQuery = $this->applyBookCopyScope(BookCopy::query(), $scope);
        $this->applyBookCopyPeriod($copiesQuery, $dateFrom, $dateTo);

        $summary = $this->summary($user, $filters, $scope);

        return [
            'summary' => $summary,
            'libraryComparison' => $this->getLibraryComparison($scope, $dateFrom, $dateTo),
            'popularBooks' => $this->getPopularBooks($scope, $dateFrom, $dateTo),
            'popularAuthors' => $this->getPopularAuthors($scope, $dateFrom, $dateTo),
            'popularCategories' => $this->getPopularCategories($scope, $dateFrom, $dateTo),
            'popularPublishers' => $this->getPopularPublishers($scope, $dateFrom, $dateTo),
            'popularBookCopies' => $this->getPopularBookCopies($scope, $dateFrom, $dateTo),
            'activeMembers' => $this->getActiveMembers($scope, $dateFrom, $dateTo),
            'copiesByStatus' => $this->getCopiesByStatus($copiesQuery),
            'copiesByBranch' => $this->getCopiesByBranch($scope, $dateFrom, $dateTo),
            'activityTimeline' => $this->getActivityTimeline($scope, $dateFrom, $dateTo),
            'periodLabel' => $periodLabel,
            'scopeLabel' => $scope->label().' - '.$periodLabel,
            'dashboardScope' => $scope,
        ];
    }

    protected function getLibraryComparison(DashboardScope $scope, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        return Library::query()
            ->when($scope->libraryId, fn (Builder $query) => $query->whereKey($scope->libraryId))
            ->when(! $scope->libraryId, function (Builder $query) {
                $query->where(function (Builder $libraryQuery) {
                    $libraryQuery->has('bookCopies')
                        ->orHas('loans')
                        ->orHas('reservations')
                        ->orHas('memberships');
                });
            })
            ->withCount([
                'bookCopies' => fn (Builder $query) => $this->applyBookCopyPeriod($this->applyBookCopyScope($query, $scope), $dateFrom, $dateTo),
                'bookCopies as available_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod($this->applyBookCopyScope($query->operationallyAvailable(), $scope), $dateFrom, $dateTo),
                'bookCopies as lost_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod($this->applyBookCopyScope($query->where('lifecycle_status', BookCopy::STATUS_LOST), $scope), $dateFrom, $dateTo),
                'bookCopies as damaged_book_copies_count' => fn (Builder $query) => $query->whereRaw('1 = 0'),
                'bookCopies as maintenance_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod($this->applyBookCopyScope($query->where('lifecycle_status', BookCopy::STATUS_MAINTENANCE), $scope), $dateFrom, $dateTo),
                'bookCopies as withdrawn_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod($this->applyBookCopyScope($query->where('lifecycle_status', BookCopy::STATUS_WITHDRAWN), $scope), $dateFrom, $dateTo),
                'loans' => fn (Builder $query) => $this->applyLoanPeriod($this->applyLoanScope($query, $scope), $dateFrom, $dateTo),
                'loans as active_loans_count' => fn (Builder $query) => $this->applyLoanPeriod($this->applyLoanScope($query->active(), $scope), $dateFrom, $dateTo),
                'loans as overdue_loans_count' => function (Builder $query) use ($scope, $dateFrom, $dateTo) {
                    $this->applyLoanPeriod($this->applyLoanScope($query, $scope), $dateFrom, $dateTo);
                    $query->whereNull('returned_at')->where(function (Builder $overdueQuery) {
                        $overdueQuery->where('status', Loan::STATUS_OVERDUE)
                            ->orWhere(fn (Builder $dateQuery) => $dateQuery->whereNotNull('due_at')->where('due_at', '<', now()));
                    });
                },
                'reservations' => fn (Builder $query) => $this->applyReservationPeriod($this->applyReservationScope($query, $scope), $dateFrom, $dateTo),
                'reservations as active_reservations_count' => fn (Builder $query) => $this->applyReservationPeriod($this->applyReservationScope($query->active(), $scope), $dateFrom, $dateTo),
                'reservations as fulfilled_reservations_count' => fn (Builder $query) => $this->applyReservationPeriod($this->applyReservationScope($query->where('status', Reservation::STATUS_FULFILLED), $scope), $dateFrom, $dateTo),
                'users as active_members_count' => function (Builder $query) use ($scope) {
                    if ($scope->isBranch()) {
                        $query->whereRaw('1 = 0');

                        return;
                    }

                    $query->where('users.role', User::ROLE_MEMBER)
                        ->where('users.is_active', true)
                        ->where('library_memberships.is_active', true);
                },
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    protected function getPopularBooks(DashboardScope $scope, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        return Book::query()
            ->select(['books.id', 'books.title', 'books.isbn'])
            ->with('authors:id,name')
            ->whereHas('bookCopies', fn (Builder $copyQuery) => $this->applyBookCopyLibraryScope($copyQuery, $scope))
            ->where(function (Builder $query) use ($scope, $dateFrom, $dateTo) {
                $query->whereHas('loans', fn (Builder $loanQuery) => $this->applyLoanPeriod($this->applyLoanScope($loanQuery, $scope), $dateFrom, $dateTo))
                    ->orWhereHas('reservations', fn (Builder $reservationQuery) => $this->applyReservationPeriod($this->applyReservationScope($reservationQuery, $scope), $dateFrom, $dateTo));
            })
            ->withCount([
                'loans as loans_count' => fn (Builder $query) => $this->applyLoanPeriod($this->applyLoanScope($query, $scope), $dateFrom, $dateTo),
                'reservations as reservations_count' => fn (Builder $query) => $this->applyReservationPeriod($this->applyReservationScope($query, $scope), $dateFrom, $dateTo),
            ])
            ->orderByDesc('loans_count')
            ->orderByDesc('reservations_count')
            ->orderBy('title')
            ->limit(7)
            ->get();
    }

    protected function getPopularAuthors(DashboardScope $scope, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        return $this->popularBookDimensionQuery(Author::query(), 'authors', 'book_author', 'author_id', $scope, $dateFrom, $dateTo);
    }

    protected function getPopularCategories(DashboardScope $scope, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        return $this->popularBookDimensionQuery(Category::query(), 'categories', 'book_category', 'category_id', $scope, $dateFrom, $dateTo);
    }

    protected function getPopularPublishers(DashboardScope $scope, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        $loanActivity = $this->loanActivityByBookSubquery($scope, $dateFrom, $dateTo);
        $reservationActivity = $this->reservationActivityByBookSubquery($scope, $dateFrom, $dateTo);

        return Publisher::query()
            ->select([
                'publishers.id',
                'publishers.name',
                DB::raw('COUNT(DISTINCT books.id) as books_count'),
                DB::raw('COALESCE(SUM(loan_activity.loans_count), 0) as loans_count'),
                DB::raw('COALESCE(SUM(reservation_activity.reservations_count), 0) as reservations_count'),
            ])
            ->join('books', 'books.publisher_id', '=', 'publishers.id')
            ->whereExists(function ($query) use ($scope) {
                $query->selectRaw('1')
                    ->from('book_copies')
                    ->whereColumn('book_copies.book_id', 'books.id');
                $this->applyBookCopyTableLibraryScope($query, $scope);
            })
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

    protected function getPopularBookCopies(DashboardScope $scope, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        return BookCopy::query()
            ->select(['book_copies.id', 'book_copies.book_id', 'book_copies.library_id', 'book_copies.branch_id', 'book_copies.inventory_code', 'book_copies.status'])
            ->with(['book:id,slug,title', 'library:id,name', 'branch:id,name'])
            ->tap(fn (Builder $query) => $this->applyBookCopyLibraryScope($query, $scope))
            ->whereHas('loans', fn (Builder $query) => $this->applyLoanPeriod($this->applyLoanScope($query, $scope), $dateFrom, $dateTo))
            ->withCount([
                'loans as loans_count' => fn (Builder $query) => $this->applyLoanPeriod($this->applyLoanScope($query, $scope), $dateFrom, $dateTo),
            ])
            ->orderByDesc('loans_count')
            ->orderBy('inventory_code')
            ->limit(7)
            ->get();
    }

    protected function getActiveMembers(DashboardScope $scope, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        return $this->activeMembersQuery($scope)
            ->where(function (Builder $query) use ($scope, $dateFrom, $dateTo) {
                $query
                    ->whereHas('loans', fn (Builder $loanQuery) => $this->applyLoanPeriod($this->applyLoanScope($loanQuery, $scope), $dateFrom, $dateTo))
                    ->orWhereHas('reservations', fn (Builder $reservationQuery) => $this->applyReservationPeriod($this->applyReservationScope($reservationQuery, $scope), $dateFrom, $dateTo));
            })
            ->with([
                'libraryMemberships' => fn ($query) => $query
                    ->when($scope->libraryId, fn ($membershipQuery) => $membershipQuery->where('library_id', $scope->libraryId))
                    ->where('is_active', true)
                    ->with('library:id,name,code'),
            ])
            ->withCount([
                'loans as loans_count' => fn (Builder $query) => $this->applyLoanPeriod($this->applyLoanScope($query, $scope), $dateFrom, $dateTo),
                'reservations as reservations_count' => fn (Builder $query) => $this->applyReservationPeriod($this->applyReservationScope($query, $scope), $dateFrom, $dateTo),
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
            ->select('lifecycle_status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('lifecycle_status')
            ->pluck('aggregate', 'lifecycle_status');

        return collect(BookCopy::statusLabels())
            ->map(fn (string $label, string $status) => (object) [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($counts[$status] ?? 0),
            ])
            ->values();
    }

    protected function getCopiesByBranch(DashboardScope $scope, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        $reservationCountSubquery = Reservation::query()
            ->selectRaw('COUNT(DISTINCT reservations.id)')
            ->whereColumn('reservations.library_id', 'branches.library_id')
            ->active()
            ->where(function (Builder $query) {
                $query->whereColumn('reservations.report_branch_id', 'branches.id')
                    ->orWhere(function (Builder $fallbackQuery) {
                        $fallbackQuery
                            ->whereNull('reservations.report_branch_id')
                            ->where('reservations.status', Reservation::STATUS_WAITING)
                            ->where('reservations.scope', Reservation::SCOPE_BRANCH)
                            ->whereColumn('reservations.branch_id', 'branches.id');
                    });
            });

        $this->applyReservationPeriod($reservationCountSubquery, $dateFrom, $dateTo);

        return Branch::query()
            ->select(['branches.id', 'branches.library_id', 'branches.name', 'branches.code'])
            ->selectSub($reservationCountSubquery, 'active_reservations_count')
            ->with('library:id,name')
            ->when($scope->libraryId, fn (Builder $query) => $query->where('branches.library_id', $scope->libraryId))
            ->when($scope->branchId, fn (Builder $query) => $query->whereKey($scope->branchId))
            ->when(! $scope->libraryId, fn (Builder $query) => $query->whereHas('bookCopies'))
            ->withCount([
                'bookCopies' => fn (Builder $query) => $this->applyBookCopyPeriod($query, $dateFrom, $dateTo),
                'bookCopies as available_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod($query->operationallyAvailable(), $dateFrom, $dateTo),
                'bookCopies as loaned_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod($query->whereHas('activeLoan'), $dateFrom, $dateTo),
                'bookCopies as lost_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod($query->where('lifecycle_status', BookCopy::STATUS_LOST), $dateFrom, $dateTo),
                'bookCopies as damaged_book_copies_count' => fn (Builder $query) => $query->whereRaw('1 = 0'),
                'bookCopies as maintenance_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod($query->where('lifecycle_status', BookCopy::STATUS_MAINTENANCE), $dateFrom, $dateTo),
                'bookCopies as withdrawn_book_copies_count' => fn (Builder $query) => $this->applyBookCopyPeriod($query->where('lifecycle_status', BookCopy::STATUS_WITHDRAWN), $dateFrom, $dateTo),
            ])
            ->orderBy('branches.name')
            ->limit(12)
            ->get();
    }

    protected function getActivityTimeline(DashboardScope $scope, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        if (! $dateFrom || ! $dateTo) {
            $dateFrom = now()->toImmutable()->subMonths(11)->startOfMonth();
            $dateTo = now()->toImmutable()->endOfMonth();
        }

        $groupByMonth = $dateFrom->diffInDays($dateTo) > 62;
        $period = CarbonPeriod::create($dateFrom, $groupByMonth ? '1 month' : '1 day', $dateTo);

        $issuedQuery = $this->applyLoanScope(Loan::query(), $scope)
            ->whereBetween('borrowed_at', [$dateFrom, $dateTo])
            ->selectRaw($this->dateBucketSelect('borrowed_at', $groupByMonth).', COUNT(*) as aggregate')
            ->groupBy('period_key');

        $returnedQuery = $this->applyLoanScope(Loan::query(), $scope, 'returned')
            ->whereNotNull('returned_at')
            ->whereBetween('returned_at', [$dateFrom, $dateTo])
            ->selectRaw($this->dateBucketSelect('returned_at', $groupByMonth).', COUNT(*) as aggregate')
            ->groupBy('period_key');

        $reservedQuery = $this->applyReservationScope(Reservation::query(), $scope)
            ->whereBetween('reserved_at', [$dateFrom, $dateTo])
            ->selectRaw($this->dateBucketSelect('reserved_at', $groupByMonth).', COUNT(*) as aggregate')
            ->groupBy('period_key');

        $issued = $issuedQuery->pluck('aggregate', 'period_key');
        $returned = $returnedQuery->pluck('aggregate', 'period_key');
        $reserved = $reservedQuery->pluck('aggregate', 'period_key');

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
            ->values();
    }

    protected function popularBookDimensionQuery(Builder $builder, string $table, string $pivot, string $foreignKey, DashboardScope $scope, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Collection
    {
        $loanActivity = $this->loanActivityByBookSubquery($scope, $dateFrom, $dateTo);
        $reservationActivity = $this->reservationActivityByBookSubquery($scope, $dateFrom, $dateTo);

        return $builder
            ->select([
                "{$table}.id",
                "{$table}.name",
                DB::raw('COUNT(DISTINCT books.id) as books_count'),
                DB::raw('COALESCE(SUM(loan_activity.loans_count), 0) as loans_count'),
                DB::raw('COALESCE(SUM(reservation_activity.reservations_count), 0) as reservations_count'),
            ])
            ->join($pivot, "{$pivot}.{$foreignKey}", '=', "{$table}.id")
            ->join('books', 'books.id', '=', "{$pivot}.book_id")
            ->whereExists(function ($query) use ($scope) {
                $query->selectRaw('1')
                    ->from('book_copies')
                    ->whereColumn('book_copies.book_id', 'books.id');
                $this->applyBookCopyTableLibraryScope($query, $scope);
            })
            ->leftJoinSub($loanActivity, 'loan_activity', fn ($join) => $join->on('loan_activity.book_id', '=', 'books.id'))
            ->leftJoinSub($reservationActivity, 'reservation_activity', fn ($join) => $join->on('reservation_activity.book_id', '=', 'books.id'))
            ->groupBy("{$table}.id", "{$table}.name")
            ->havingRaw('COALESCE(SUM(loan_activity.loans_count), 0) + COALESCE(SUM(reservation_activity.reservations_count), 0) > 0')
            ->orderByDesc('loans_count')
            ->orderByDesc('reservations_count')
            ->orderBy("{$table}.name")
            ->limit(7)
            ->get();
    }

    protected function loanActivityByBookSubquery(DashboardScope $scope, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Builder
    {
        $query = Loan::query()
            ->selectRaw('book_copies.book_id as book_id, COUNT(*) as loans_count')
            ->join('book_copies', 'book_copies.id', '=', 'loans.book_copy_id');

        $this->applyLoanScope($query, $scope);
        $this->applyLoanPeriod($query, $dateFrom, $dateTo);

        return $query->groupBy('book_copies.book_id');
    }

    protected function reservationActivityByBookSubquery(DashboardScope $scope, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Builder
    {
        $query = Reservation::query()
            ->selectRaw('book_id, COUNT(*) as reservations_count');

        $this->applyReservationScope($query, $scope);
        $this->applyReservationPeriod($query, $dateFrom, $dateTo);

        return $query->groupBy('book_id');
    }

    protected function activeMembersQuery(DashboardScope $scope): Builder
    {
        return User::query()
            ->select(['users.id', 'users.name', 'users.membership_number'])
            ->where('users.role', User::ROLE_MEMBER)
            ->where('users.is_active', true)
            ->when(
                $scope->libraryId,
                fn (Builder $query) => $query->whereHas('libraryMemberships', fn (Builder $membershipQuery) => $membershipQuery
                    ->where('library_id', $scope->libraryId)
                    ->where('is_active', true))
            );
    }

    protected function newMembersCount(DashboardScope $scope, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): int
    {
        return $this->activeMembersQuery($scope)
            ->when($dateFrom && $dateTo, fn (Builder $query) => $query->whereBetween('created_at', [$dateFrom, $dateTo]))
            ->count();
    }

    protected function applyBookCopyScope(Builder $query, DashboardScope $scope): Builder
    {
        return $query
            ->when($scope->libraryId, fn (Builder $builder) => $builder->where($query->getModel()->qualifyColumn('library_id'), $scope->libraryId))
            ->when($scope->branchId, fn (Builder $builder) => $builder->where($query->getModel()->qualifyColumn('branch_id'), $scope->branchId));
    }

    protected function applyBookCopyLibraryScope(Builder $query, DashboardScope $scope): Builder
    {
        return $query->when($scope->libraryId, fn (Builder $builder) => $builder->where($query->getModel()->qualifyColumn('library_id'), $scope->libraryId));
    }

    protected function applyBookCopyTableScope($query, DashboardScope $scope): void
    {
        if ($scope->libraryId) {
            $query->where('book_copies.library_id', $scope->libraryId);
        }

        if ($scope->branchId) {
            $query->where('book_copies.branch_id', $scope->branchId);
        }
    }

    protected function applyBookCopyTableLibraryScope($query, DashboardScope $scope): void
    {
        if ($scope->libraryId) {
            $query->where('book_copies.library_id', $scope->libraryId);
        }
    }

    protected function applyLoanScope(Builder $query, DashboardScope $scope, string $event = 'issued'): Builder
    {
        $query->when($scope->libraryId, fn (Builder $builder) => $builder->where($query->getModel()->qualifyColumn('library_id'), $scope->libraryId));

        if ($scope->branchId) {
            $branchColumn = $event === 'returned' ? 'returned_branch_id' : 'issued_branch_id';
            $query->where($query->getModel()->qualifyColumn($branchColumn), $scope->branchId);
        }

        return $query;
    }

    protected function applyReservationScope(Builder $query, DashboardScope $scope): Builder
    {
        $query->when($scope->libraryId, fn (Builder $builder) => $builder->where($query->getModel()->qualifyColumn('library_id'), $scope->libraryId));

        if ($scope->branchId) {
            $query->where(function (Builder $branchQuery) use ($scope) {
                $branchQuery
                    ->where($branchQuery->getModel()->qualifyColumn('report_branch_id'), $scope->branchId)
                    ->orWhere(function (Builder $fallbackQuery) use ($scope) {
                        $fallbackQuery
                            ->whereNull('report_branch_id')
                            ->where('status', Reservation::STATUS_WAITING)
                            ->where('scope', Reservation::SCOPE_BRANCH)
                            ->where('branch_id', $scope->branchId);
                    });
            });
        }

        return $query;
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
                            ->orWhere(fn (Builder $returnedQuery) => $returnedQuery->whereNotNull('returned_at')->whereBetween('returned_at', [$dateFrom, $dateTo]));
                    });
                })
                ->orWhereHas('statusHistories', fn (Builder $historyQuery) => $historyQuery->whereBetween('changed_at', [$dateFrom, $dateTo]));
        });
    }

    protected function applyLoanPeriod(Builder $query, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Builder
    {
        if (! $dateFrom || ! $dateTo) {
            return $query;
        }

        return $query->whereBetween('borrowed_at', [$dateFrom, $dateTo]);
    }

    protected function applyReservationPeriod(Builder $query, ?CarbonImmutable $dateFrom, ?CarbonImmutable $dateTo): Builder
    {
        if (! $dateFrom || ! $dateTo) {
            return $query;
        }

        return $query->whereBetween('reserved_at', [$dateFrom, $dateTo]);
    }

    protected function dateBucketSelect(string $column, bool $groupByMonth): string
    {
        $qualifiedColumn = DB::connection()->getQueryGrammar()->wrap($column);
        $driver = DB::connection()->getDriverName();
        $format = $groupByMonth ? '%Y-%m-01' : '%Y-%m-%d';

        if ($driver === 'sqlite') {
            return "strftime('{$format}', {$qualifiedColumn}) as period_key";
        }

        return "DATE_FORMAT({$qualifiedColumn}, '{$format}') as period_key";
    }
}
