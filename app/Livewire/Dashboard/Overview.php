<?php

namespace App\Livewire\Dashboard;

use App\Models\BookCopy;
use App\Models\Loan;
use App\Queries\Reports\GetDashboardReportDataQuery;
use App\Support\Reports\ResolveDashboardFilters;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class Overview extends Component
{
    #[Url(as: 'period', history: true)]
    public string $period = 'this_month';

    #[Url(as: 'date_from', history: true)]
    public ?string $dateFrom = null;

    #[Url(as: 'date_to', history: true)]
    public ?string $dateTo = null;

    /**
     * @var array<string, mixed>
     */
    protected array $chartPayload = [];

    public function mount(array $filters = []): void
    {
        $this->period = (string) ($filters['period'] ?? 'this_month');
        $this->dateFrom = $filters['date_from']?->format('Y-m-d');
        $this->dateTo = $filters['date_to']?->format('Y-m-d');
    }

    public function updatedPeriod(string $value): void
    {
        if ($value !== 'custom') {
            $this->dateFrom = null;
            $this->dateTo = null;
        }
    }

    public function applyFilters(): void
    {
        $this->validate();
    }

    public function resetFilters(): void
    {
        $this->reset(['dateFrom', 'dateTo']);
        $this->period = 'this_month';
    }

    public function rendered(): void
    {
        $this->dispatch('dashboard-charts-updated', payload: $this->chartPayload);
    }

    protected function rules(): array
    {
        return [
            'period' => ['required', Rule::in(['all', 'today', '7_days', '30_days', 'this_week', 'this_month', 'last_month', 'this_quarter', 'this_year', 'custom'])],
            'dateFrom' => ['nullable', 'date', 'required_if:period,custom'],
            'dateTo' => ['nullable', 'date', 'required_if:period,custom', 'after_or_equal:dateFrom'],
        ];
    }

    public function render(GetDashboardReportDataQuery $query, ResolveDashboardFilters $resolveDashboardFilters)
    {
        $actor = Auth::user();
        abort_if(! $actor, 403);

        $filters = $resolveDashboardFilters->handle($this->period, $this->dateFrom, $this->dateTo);
        $report = $query->handle($actor, $filters);
        $previousFilters = $resolveDashboardFilters->previous($filters);
        $previousReport = $previousFilters ? $query->handle($actor, $previousFilters) : null;

        $timeline = collect($report['activityTimeline']);
        $cards = $this->summaryCards($report, $previousReport);
        $snapshot = $this->snapshotItems($report, $filters);
        $alerts = $this->alertItems($report);
        $quickActions = $this->quickActions($actor->effectiveRole());
        $copiesBreakdown = $this->copiesBreakdown($report);

        $this->chartPayload = [
            'timeline' => [
                'categories' => $timeline->pluck('label')->values()->all(),
                'series' => [
                    ['name' => 'Išduota', 'data' => $timeline->pluck('issued_loans_count')->values()->all()],
                    ['name' => 'Grąžinta', 'data' => $timeline->pluck('returned_loans_count')->values()->all()],
                    ['name' => 'Rezervuota', 'data' => $timeline->pluck('reservations_count')->values()->all()],
                ],
            ],
            'copies' => [
                'labels' => collect($report['copiesByStatus'])->pluck('label')->values()->all(),
                'series' => collect($report['copiesByStatus'])->pluck('count')->map(fn ($count) => (int) $count)->values()->all(),
            ],
        ];

        return view('livewire.dashboard.overview', [
            'report' => $report,
            'filters' => $filters,
            'cards' => $cards,
            'snapshot' => $snapshot,
            'alerts' => $alerts,
            'quickActions' => $quickActions,
            'copiesBreakdown' => $copiesBreakdown,
            'timeline' => $timeline,
            'exportQuery' => $this->exportQuery(),
            'chartPayload' => $this->chartPayload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>|null  $previousReport
     * @return array<int, array<string, mixed>>
     */
    protected function summaryCards(array $report, ?array $previousReport): array
    {
        $todayIssued = $this->todayIssuedCount();

        return [
            $this->card(
                'Knygų kopijos',
                (int) $report['summary']['book_copies_count'],
                'Iš viso',
                'book-open-text',
                'text-teal-600 bg-teal-50 dark:bg-teal-500/10 dark:text-teal-300',
                $previousReport['summary']['book_copies_count'] ?? null,
            ),
            $this->card(
                'Išduotos knygos',
                (int) $report['summary']['active_loans_count'],
                'Šiuo metu',
                'chevrons-up-down',
                'text-blue-600 bg-blue-50 dark:bg-blue-500/10 dark:text-blue-300',
                $previousReport['summary']['active_loans_count'] ?? null,
            ),
            $this->card(
                'Rezervacijos',
                (int) $report['summary']['active_reservations_count'],
                'Aktyvios',
                'folder-git-2',
                'text-amber-600 bg-amber-50 dark:bg-amber-500/10 dark:text-amber-300',
                $previousReport['summary']['active_reservations_count'] ?? null,
            ),
            $this->card(
                'Aktyvūs nariai',
                (int) $report['summary']['active_members_count'],
                'Šiuo metu',
                'users',
                'text-violet-600 bg-violet-50 dark:bg-violet-500/10 dark:text-violet-300',
                $previousReport['summary']['active_members_count'] ?? null,
            ),
            $this->card(
                'Vėluojančios knygos',
                (int) $report['summary']['overdue_loans_count'],
                'Reikalauja dėmesio',
                'bell',
                'text-red-600 bg-red-50 dark:bg-red-500/10 dark:text-red-300',
                $previousReport['summary']['overdue_loans_count'] ?? null,
            ),
            [
                'label' => 'Šiandien išduota',
                'value' => $todayIssued,
                'caption' => 'Knygos',
                'icon' => 'layout-grid',
                'icon_classes' => 'text-cyan-600 bg-cyan-50 dark:bg-cyan-500/10 dark:text-cyan-300',
                'delta' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function card(
        string $label,
        int $value,
        string $caption,
        string $icon,
        string $iconClasses,
        ?int $previousValue
    ): array {
        $delta = $previousValue === null ? null : $value - $previousValue;

        return [
            'label' => $label,
            'value' => $value,
            'caption' => $caption,
            'icon' => $icon,
            'icon_classes' => $iconClasses,
            'delta' => $delta,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    protected function snapshotItems(array $report, array $filters): array
    {
        return [
            [
                'label' => 'Nauji nariai',
                'value' => $this->newMembersCount($filters),
                'caption' => $filters['period_label'],
                'accent' => 'emerald',
            ],
            [
                'label' => 'Naujos kopijos',
                'value' => (int) $report['summary']['book_copies_count'],
                'caption' => 'Kopijos',
                'accent' => 'sky',
            ],
            [
                'label' => 'Šiandien grąžinta',
                'value' => $this->todayReturnedCount(),
                'caption' => 'Knygos',
                'accent' => 'teal',
            ],
            [
                'label' => 'Laukiančios rezervacijos',
                'value' => (int) $report['summary']['active_reservations_count'],
                'caption' => 'Aktyvios',
                'accent' => 'amber',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, array<string, mixed>>
     */
    protected function alertItems(array $report): array
    {
        return [
            [
                'title' => $report['summary']['overdue_loans_count'] . ' knygų vėluoja',
                'description' => 'Patikrink išduotas knygas ir susisiek su nariais.',
                'route' => route('loans.index'),
                'link' => 'Peržiūrėti sąrašą',
                'tone' => 'warning',
            ],
            [
                'title' => $report['summary']['active_reservations_count'] . ' rezervacijos laukia',
                'description' => 'Pažiūrėk, ar eilėje esantiems nariams jau galima pasiūlyti kopiją.',
                'route' => route('reservations.index'),
                'link' => 'Peržiūrėti rezervacijas',
                'tone' => 'info',
            ],
            [
                'title' => $report['summary']['damaged_book_copies_count'] . ' sugadintos kopijos',
                'description' => 'Peržiūrėk būkles ir nuspręsk, ką reikia tvarkyti ar nurašyti.',
                'route' => route('manage.book-copies.index', ['status' => BookCopy::STATUS_DAMAGED]),
                'link' => 'Peržiūrėti',
                'tone' => 'warning',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function quickActions(string $role): array
    {
        $actions = [
            [
                'label' => 'Pridėti kopiją',
                'route' => route('manage.book-copies.create'),
            ],
            [
                'label' => 'Išduoti knygą',
                'route' => route('loans.index'),
            ],
            [
                'label' => 'Registruoti grąžinimą',
                'route' => route('loans.index'),
            ],
            [
                'label' => 'Naujas narys',
                'route' => route('manage.users.create'),
            ],
        ];

        if ($role === 'superadministratorius') {
            array_unshift($actions, [
                'label' => 'Nauja knyga',
                'route' => route('manage.books.create'),
            ]);
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, array<string, mixed>>
     */
    protected function copiesBreakdown(array $report): array
    {
        $total = max((int) $report['summary']['book_copies_count'], 1);

        return collect($report['copiesByStatus'])
            ->map(function ($row) use ($total) {
                return [
                    'label' => $row->label,
                    'count' => (int) $row->count,
                    'share' => round(((int) $row->count / $total) * 100, 1),
                    'color' => match ($row->status) {
                        'laisva' => '#0f9f6e',
                        'išduota' => '#2563eb',
                        'prarasta' => '#f97316',
                        'sugadinta' => '#ef4444',
                        default => '#d4d4d8',
                    },
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function exportQuery(): array
    {
        return array_filter([
            'period' => $this->period,
            'date_from' => $this->period === 'custom' ? $this->dateFrom : null,
            'date_to' => $this->period === 'custom' ? $this->dateTo : null,
        ], fn ($value) => filled($value));
    }

    protected function todayIssuedCount(): int
    {
        $actor = Auth::user();

        return Loan::query()
            ->when(! $actor?->isSuperAdmin(), fn ($query) => $query->where('library_id', $actor?->activeLibraryId()))
            ->whereBetween('borrowed_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();
    }

    protected function todayReturnedCount(): int
    {
        $actor = Auth::user();

        return Loan::query()
            ->when(! $actor?->isSuperAdmin(), fn ($query) => $query->where('library_id', $actor?->activeLibraryId()))
            ->whereNotNull('returned_at')
            ->whereBetween('returned_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();
    }

    protected function newMembersCount(array $filters): int
    {
        $actor = Auth::user();

        return \App\Models\User::query()
            ->where('role', 'narys')
            ->when(! $actor?->isSuperAdmin(), fn ($query) => $query->whereHas('libraryMemberships', fn ($membershipQuery) => $membershipQuery
                ->where('library_id', $actor?->activeLibraryId())
                ->where('is_active', true)))
            ->when($filters['date_from'] && $filters['date_to'], fn ($query) => $query->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]))
            ->count();
    }
}







