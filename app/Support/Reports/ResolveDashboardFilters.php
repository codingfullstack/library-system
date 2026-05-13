<?php

namespace App\Support\Reports;

use Carbon\CarbonImmutable;
use Throwable;

class ResolveDashboardFilters
{
    /**
     * @return array{
     *     period: string,
     *     date_from: ?CarbonImmutable,
     *     date_to: ?CarbonImmutable,
     *     period_label: string
     * }
     */
    public function handle(?string $period = 'all', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $period = in_array($period, [
            'all',
            'today',
            '7_days',
            '30_days',
            'this_week',
            'this_month',
            'last_month',
            'this_quarter',
            'this_year',
            'custom',
        ], true) ? (string) $period : 'all';

        [$resolvedFrom, $resolvedTo, $label] = match ($period) {
            'today' => [
                now()->toImmutable()->startOfDay(),
                now()->toImmutable()->endOfDay(),
                'Šiandiena',
            ],
            'this_week' => [
                now()->toImmutable()->startOfWeek(),
                now()->toImmutable()->endOfWeek(),
                'Ši savaitė',
            ],
            '7_days' => [
                now()->toImmutable()->subDays(6)->startOfDay(),
                now()->toImmutable()->endOfDay(),
                'Paskutinės 7 dienos',
            ],
            '30_days' => [
                now()->toImmutable()->subDays(29)->startOfDay(),
                now()->toImmutable()->endOfDay(),
                'Paskutinės 30 dienų',
            ],
            'this_month' => [
                now()->toImmutable()->startOfMonth(),
                now()->toImmutable()->endOfMonth(),
                'Šis mėnuo',
            ],
            'last_month' => [
                now()->toImmutable()->subMonthNoOverflow()->startOfMonth(),
                now()->toImmutable()->subMonthNoOverflow()->endOfMonth(),
                'Praėjęs mėnuo',
            ],
            'this_quarter' => [
                now()->toImmutable()->startOfQuarter(),
                now()->toImmutable()->endOfQuarter(),
                'Šis ketvirtis',
            ],
            'this_year' => [
                now()->toImmutable()->startOfYear(),
                now()->toImmutable()->endOfDay(),
                'Šie metai',
            ],
            'custom' => $this->resolveCustomRange($dateFrom, $dateTo),
            default => [null, null, 'Visas laikotarpis'],
        };

        return [
            'period' => $period,
            'date_from' => $resolvedFrom,
            'date_to' => $resolvedTo,
            'period_label' => $label,
        ];
    }

    /**
     * @param  array{
     *     period: string,
     *     date_from: ?CarbonImmutable,
     *     date_to: ?CarbonImmutable,
     *     period_label: string
     * }  $filters
     * @return array{
     *     period: string,
     *     date_from: ?CarbonImmutable,
     *     date_to: ?CarbonImmutable,
     *     period_label: string
     * }|null
     */
    public function previous(array $filters): ?array
    {
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        if (! $dateFrom || ! $dateTo) {
            return null;
        }

        $duration = $dateTo->diffInSeconds($dateFrom);
        $previousTo = $dateFrom->subSecond();
        $previousFrom = $previousTo->subSeconds($duration);

        return [
            'period' => 'custom',
            'date_from' => $previousFrom,
            'date_to' => $previousTo,
            'period_label' => sprintf('%s - %s', $previousFrom->format('Y-m-d'), $previousTo->format('Y-m-d')),
        ];
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable, 2: string}
     */
    protected function resolveCustomRange(?string $dateFrom, ?string $dateTo): array
    {
        if (! $dateFrom || ! $dateTo) {
            return [null, null, 'Pasirinktas intervalas'];
        }

        try {
            $from = CarbonImmutable::parse($dateFrom)->startOfDay();
            $to = CarbonImmutable::parse($dateTo)->endOfDay();
        } catch (Throwable) {
            return [null, null, 'Pasirinktas intervalas'];
        }

        if ($to->lt($from)) {
            return [null, null, 'Pasirinktas intervalas'];
        }

        return [
            $from,
            $to,
            sprintf('%s - %s', $from->format('Y-m-d'), $to->format('Y-m-d')),
        ];
    }
}








