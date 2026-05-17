<x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
    <div class="min-h-screen bg-[#f7f8fa] dark:bg-zinc-950">
        <div class="mx-auto max-w-[1600px] py-5">
            <div class="rounded-[28px] border border-zinc-200/80 bg-white shadow-[0_18px_50px_rgba(15,23,42,0.06)] dark:border-zinc-800 dark:bg-zinc-900">
                <div class="space-y-6 px-6 py-6">
                    <script id="dashboard-chart-payload" type="application/json">
                        @json($chartPayload)
                    </script>

                    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
                        <div class="min-w-0 xl:max-w-2xl">
                            <h1 class="text-[42px] font-bold leading-none tracking-tight text-zinc-950 dark:text-white">Apžvalga</h1>
                            <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                                Pagrindinė bibliotekos statistika ir veiklos suvestinė
                            </p>
                        </div>

                        <form wire:submit="applyFilters" class="min-w-0 xl:w-auto">
                            <div class="grid gap-3 xl:grid-flow-col xl:auto-cols-max xl:items-center xl:justify-end">
                                <div class="inline-flex h-11 items-center gap-3 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-700 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                                    <flux:icon.calendar-days class="size-4 text-zinc-500" />
                                    <span>{{ $filters['period_label'] }}</span>
                                </div>

                                <div class="xl:w-[220px]">
                                    <select id="dashboard-period" wire:model.live="period" class="app-input h-11 rounded-2xl border-zinc-200 bg-white shadow-none dark:border-zinc-700 dark:bg-zinc-900">
                                        <option value="all">Visas laikotarpis</option>
                                        <option value="today">Šiandiena</option>
                                        <option value="this_week">Ši savaitė</option>
                                        <option value="7_days">Paskutinės 7 dienos</option>
                                        <option value="30_days">Paskutinės 30 dienų</option>
                                        <option value="this_month">Šis mėnuo</option>
                                        <option value="last_month">Praėjęs mėnuo</option>
                                        <option value="this_quarter">Šis ketvirtis</option>
                                        <option value="this_year">Šie metai</option>
                                        <option value="custom">Pasirinktas intervalas</option>
                                    </select>
                                </div>

                                @if($period === 'custom')
                                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-flow-col xl:auto-cols-max">
                                        <input id="dashboard-date-from" type="date" wire:model.live="dateFrom" class="app-input h-11 rounded-2xl border-zinc-200 bg-white shadow-none dark:border-zinc-700 dark:bg-zinc-900 xl:w-[170px]">
                                        <input id="dashboard-date-to" type="date" wire:model.live="dateTo" class="app-input h-11 rounded-2xl border-zinc-200 bg-white shadow-none dark:border-zinc-700 dark:bg-zinc-900 xl:w-[170px]">
                                    </div>
                                @endif

                                <div class="inline-flex overflow-hidden rounded-2xl border border-emerald-700 shadow-sm">
                                    <a href="{{ route('dashboard.export', ['format' => 'xls'] + $exportQuery) }}" class="inline-flex h-11 items-center justify-center bg-emerald-700 px-4 text-sm font-semibold text-white transition hover:bg-emerald-600">
                                        Eksportuoti
                                    </a>
                                    <a href="{{ route('dashboard.export', ['format' => 'csv'] + $exportQuery) }}" title="Atsisiųsti CSV eksportą" aria-label="Atsisiųsti CSV eksportą" class="inline-flex h-11 w-11 items-center justify-center bg-emerald-700 text-white transition hover:bg-emerald-600">
                                        <flux:icon.chevron-down class="size-4" />
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                        @foreach($cards as $card)
                            <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl {{ $card['icon_classes'] }}">
                                        @switch($card['icon'])
                                            @case('book-open-text')
                                                <flux:icon.book-open-text class="size-5" />
                                                @break
                                            @case('chevrons-up-down')
                                                <flux:icon.chevrons-up-down class="size-5" />
                                                @break
                                            @case('folder-git-2')
                                                <flux:icon.folder-git-2 class="size-5" />
                                                @break
                                            @case('users')
                                                <flux:icon.users class="size-5" />
                                                @break
                                            @case('bell')
                                                <flux:icon.bell class="size-5" />
                                                @break
                                            @default
                                                <flux:icon.layout-grid class="size-5" />
                                        @endswitch
                                    </div>

                                    @if(! is_null($card['delta']))
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold {{ $card['delta'] >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300' }}">
                                            <flux:icon.arrow-trending-up class="size-3.5 {{ $card['delta'] < 0 ? 'rotate-180' : '' }}" />
                                            {{ abs($card['delta']) }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-4 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $card['label'] }}</div>
                                <div class="mt-2 text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ $card['value'] }}</div>
                                <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $card['caption'] }}</div>
                            </section>
                        @endforeach
                    </div>

                    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(380px,0.9fr)]">
                        <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex flex-col gap-4 border-b border-zinc-200/80 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700">
                                <div>
                                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Išdavimų, grąžinimų ir rezervacijų dinamika</h2>
                                </div>
                            </div>

                            <div class="px-4 py-4">
                                <div wire:ignore id="dashboard-activity-chart" class="dashboard-chart-surface min-h-[320px]"></div>
                            </div>
                        </section>

                        <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="border-b border-zinc-200/80 px-5 py-4 dark:border-zinc-700">
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Egzempliorių būsenos</h2>
                            </div>

                            <div class="grid gap-6 px-5 py-5 xl:grid-cols-[220px_minmax(0,1fr)]">
                                <div wire:ignore id="dashboard-copies-chart" class="dashboard-chart-surface min-h-[220px]"></div>

                                <div class="space-y-3">
                                    @foreach($copiesBreakdown as $item)
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="flex items-center gap-3">
                                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $item['label'] }}</span>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $item['count'] }}</div>
                                                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ number_format($item['share'], 1) }}%</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.08fr)_minmax(0,1.08fr)_minmax(320px,0.84fr)]">
                        <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex items-center justify-between border-b border-zinc-200/80 px-5 py-4 dark:border-zinc-700">
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Populiariausios knygos</h2>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="app-table">
                                    <thead class="app-table-head">
                                        <tr>
                                            <th class="app-th">#</th>
                                            <th class="app-th">Knyga</th>
                                            <th class="app-th text-right">Išdavimai</th>
                                            <th class="app-th text-right">Rezervacijos</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                        @forelse($report['popularBooks'] as $index => $book)
                                            <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                                <td class="app-td">{{ $index + 1 }}</td>
                                                <td class="app-td">
                                                    <div class="font-semibold text-zinc-950 dark:text-white">{{ $book->title }}</div>
                                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $book->authors->pluck('name')->take(2)->implode(', ') ?: ($book->isbn ?: '-') }}</div>
                                                </td>
                                                <td class="app-td text-right font-semibold text-zinc-950 dark:text-white">{{ $book->loans_count }}</td>
                                                <td class="app-td text-right">{{ $book->reservations_count }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="app-td text-center text-zinc-500 dark:text-zinc-400">Duomenų dar nėra.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex items-center justify-between border-b border-zinc-200/80 px-5 py-4 dark:border-zinc-700">
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Aktyviausi nariai</h2>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="app-table">
                                    <thead class="app-table-head">
                                        <tr>
                                            <th class="app-th">#</th>
                                            <th class="app-th">Narys</th>
                                            <th class="app-th text-right">Išdavimai</th>
                                            <th class="app-th text-right">Rezervacijos</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                        @forelse($report['activeMembers'] as $index => $member)
                                            <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                                <td class="app-td">{{ $index + 1 }}</td>
                                                <td class="app-td">
                                                    <div class="font-semibold text-zinc-950 dark:text-white">{{ $member->name }}</div>
                                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $member->membership_number ?: '-' }}</div>
                                                </td>
                                                <td class="app-td text-right font-semibold text-zinc-950 dark:text-white">{{ $member->loans_count }}</td>
                                                <td class="app-td text-right">{{ $member->reservations_count }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="app-td text-center text-zinc-500 dark:text-zinc-400">Duomenų dar nėra.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex items-center justify-between border-b border-zinc-200/80 px-5 py-4 dark:border-zinc-700">
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Veiklos suvestinė</h2>
                            </div>

                            <div class="space-y-4 px-5 py-5">
                                @foreach($snapshot as $item)
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $item['label'] }}</div>
                                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $item['caption'] }}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-2xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ $item['value'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                        <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="border-b border-zinc-200/80 px-5 py-4 dark:border-zinc-700">
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Pastabos ir įspėjimai</h2>
                            </div>

                            <div class="grid gap-3 px-5 py-5 md:grid-cols-3">
                                @foreach($alerts as $item)
                                    @php
                                        $alertPalette = match ($item['tone'] ?? 'info') {
                                            'warning' => [
                                                'wrapper' => 'border-amber-200 bg-amber-50/80 hover:border-amber-300 hover:bg-amber-50 dark:border-amber-900/50 dark:bg-amber-500/10 dark:hover:border-amber-700 dark:hover:bg-amber-500/15',
                                                'iconWrap' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                                                'link' => 'text-amber-700 dark:text-amber-300',
                                                'icon' => 'exclamation-triangle',
                                            ],
                                            default => [
                                                'wrapper' => 'border-sky-200 bg-sky-50/80 hover:border-sky-300 hover:bg-sky-50 dark:border-sky-900/50 dark:bg-sky-500/10 dark:hover:border-sky-700 dark:hover:bg-sky-500/15',
                                                'iconWrap' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                                                'link' => 'text-sky-700 dark:text-sky-300',
                                                'icon' => 'bell-alert',
                                            ],
                                        };
                                    @endphp

                                    <a href="{{ $item['route'] }}" class="rounded-2xl border p-4 transition {{ $alertPalette['wrapper'] }}">
                                        <div class="flex items-start gap-3">
                                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl {{ $alertPalette['iconWrap'] }}">
                                                @if($alertPalette['icon'] === 'exclamation-triangle')
                                                    <flux:icon.exclamation-triangle class="size-5" />
                                                @else
                                                    <flux:icon.bell-alert class="size-5" />
                                                @endif
                                            </span>

                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $item['title'] }}</div>
                                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $item['description'] }}</p>
                                                <div class="mt-3 text-sm font-medium {{ $alertPalette['link'] }}">{{ $item['link'] }}</div>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </section>

                        <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="border-b border-zinc-200/80 px-5 py-4 dark:border-zinc-700">
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Greiti veiksmai</h2>
                            </div>

                            <div class="grid gap-3 px-5 py-5 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach($quickActions as $index => $action)
                                    @php
                                        $actionPalette = match ($index % 4) {
                                            0 => [
                                                'wrapper' => 'border-emerald-200 bg-emerald-50/70 hover:border-emerald-300 hover:bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-500/10 dark:hover:border-emerald-700 dark:hover:bg-emerald-500/15',
                                                'iconWrap' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                                                'icon' => 'plus',
                                            ],
                                            1 => [
                                                'wrapper' => 'border-sky-200 bg-sky-50/70 hover:border-sky-300 hover:bg-sky-50 dark:border-sky-900/40 dark:bg-sky-500/10 dark:hover:border-sky-700 dark:hover:bg-sky-500/15',
                                                'iconWrap' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                                                'icon' => 'book-open-text',
                                            ],
                                            2 => [
                                                'wrapper' => 'border-amber-200 bg-amber-50/70 hover:border-amber-300 hover:bg-amber-50 dark:border-amber-900/40 dark:bg-amber-500/10 dark:hover:border-amber-700 dark:hover:bg-amber-500/15',
                                                'iconWrap' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                                                'icon' => 'chevrons-up-down',
                                            ],
                                            default => [
                                                'wrapper' => 'border-violet-200 bg-violet-50/70 hover:border-violet-300 hover:bg-violet-50 dark:border-violet-900/40 dark:bg-violet-500/10 dark:hover:border-violet-700 dark:hover:bg-violet-500/15',
                                                'iconWrap' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
                                                'icon' => 'users',
                                            ],
                                        };
                                    @endphp

                                    <a href="{{ $action['route'] }}" class="flex min-h-[124px] min-w-0 flex-col items-center justify-center gap-3 rounded-2xl border px-3 py-4 text-center transition {{ $actionPalette['wrapper'] }}">
                                        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl {{ $actionPalette['iconWrap'] }}">
                                            @switch($actionPalette['icon'])
                                                @case('plus')
                                                    <flux:icon.plus class="size-5" />
                                                    @break
                                                @case('book-open-text')
                                                    <flux:icon.book-open-text class="size-5" />
                                                    @break
                                                @case('chevrons-up-down')
                                                    <flux:icon.chevrons-up-down class="size-5" />
                                                    @break
                                                @default
                                                    <flux:icon.users class="size-5" />
                                            @endswitch
                                        </span>

                                        <span class="block max-w-[9rem] text-balance text-sm font-semibold leading-snug text-zinc-800 dark:text-zinc-100">
                                            {{ $action['label'] }}
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    </div>

                    @if(auth()->user()?->isSuperAdmin() && $report['libraryComparison']->isNotEmpty())
                        <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="border-b border-zinc-200/80 px-5 py-4 dark:border-zinc-700">
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Bibliotekų palyginimas</h2>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="app-table">
                                    <thead class="app-table-head">
                                        <tr>
                                            <th class="app-th">Biblioteka</th>
                                            <th class="app-th text-right">Egz.</th>
                                            <th class="app-th text-right">Laisvi</th>
                                            <th class="app-th text-right">Išduota</th>
                                            <th class="app-th text-right">Rezervacijos</th>
                                            <th class="app-th text-right">Nariai</th>
                                            <th class="app-th text-right">Vėluoja</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                        @foreach($report['libraryComparison'] as $library)
                                            <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                                <td class="app-td">
                                                    <div class="font-semibold text-zinc-950 dark:text-white">{{ $library->name }}</div>
                                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $library->code ?: '-' }}</div>
                                                </td>
                                                <td class="app-td text-right">{{ $library->book_copies_count }}</td>
                                                <td class="app-td text-right">{{ $library->available_book_copies_count }}</td>
                                                <td class="app-td text-right">{{ $library->active_loans_count }}</td>
                                                <td class="app-td text-right">{{ $library->active_reservations_count }}</td>
                                                <td class="app-td text-right">{{ $library->active_members_count }}</td>
                                                <td class="app-td text-right">{{ $library->overdue_loans_count }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @once
        <style>
            .dashboard-chart-surface .apexcharts-tooltip,
            .dashboard-chart-surface .apexcharts-xaxistooltip {
                border-radius: 0.75rem !important;
                box-shadow: 0 18px 45px rgba(15, 23, 42, 0.16) !important;
            }

            .dark .dashboard-chart-surface .apexcharts-tooltip,
            .dark .dashboard-chart-surface .apexcharts-xaxistooltip {
                border: 1px solid rgb(63 63 70) !important;
                background: rgb(24 24 27) !important;
                color: rgb(244 244 245) !important;
                box-shadow: 0 18px 45px rgba(0, 0, 0, 0.35) !important;
            }

            .dark .dashboard-chart-surface .apexcharts-tooltip-title {
                border-bottom: 1px solid rgb(63 63 70) !important;
                background: rgb(39 39 42) !important;
                color: rgb(244 244 245) !important;
            }

            .dark .dashboard-chart-surface .apexcharts-tooltip-text,
            .dark .dashboard-chart-surface .apexcharts-tooltip-series-group,
            .dark .dashboard-chart-surface .apexcharts-xaxistooltip-text,
            .dark .dashboard-chart-surface .apexcharts-legend-text {
                color: rgb(228 228 231) !important;
            }

            .dark .dashboard-chart-surface .apexcharts-xaxistooltip-bottom::before {
                border-bottom-color: rgb(63 63 70) !important;
            }

            .dark .dashboard-chart-surface .apexcharts-xaxistooltip-bottom::after {
                border-bottom-color: rgb(24 24 27) !important;
            }
        </style>

        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
            <script>
                (() => {
                    const chartStore = window.__dashboardChartStore ?? (window.__dashboardChartStore = {});
                    const handlerKey = '__dashboardChartsUpdatedHandler';
                    const lastPayloadKey = '__dashboardChartsLastPayload';
                    const navigatedKey = '__dashboardChartsNavigatedHandler';
                    const themeObserverKey = '__dashboardChartsThemeObserver';

                    const isTamsi = () => document.documentElement.classList.contains('dark');

                    const chartTextColor = () => isTamsi() ? '#d4d4d8' : '#52525b';
                    const mutedTextColor = () => isTamsi() ? '#e4e4e7' : '#71717a';
                    const borderColor = () => isTamsi() ? '#27272a' : '#e4e4e7';

                    const baseTheme = () => ({
                        mode: isTamsi() ? 'dark' : 'light',
                        foreColor: chartTextColor(),
                    });

                    const payloadFromDom = () => {
                        const node = document.getElementById('dashboard-chart-payload');

                        if (!node) {
                            return null;
                        }

                        try {
                            return JSON.parse(node.textContent || '{}');
                        } catch (error) {
                            console.warn('Dashboard payload parse failed', error);
                            return null;
                        }
                    };

                    const destroyChart = (key) => {
                        if (!chartStore[key]) {
                            return;
                        }

                        try {
                            chartStore[key].destroy();
                        } catch (error) {
                            console.warn('Dashboard chart destroy failed', key, error);
                        }

                        delete chartStore[key];
                    };

                    const renderOrUpdate = (key, selector, options) => {
                        const element = document.querySelector(selector);

                        if (!element || typeof ApexCharts === 'undefined') {
                            return false;
                        }

                        if (chartStore[key] && chartStore[key].el !== element) {
                            destroyChart(key);
                        }

                        if (chartStore[key]) {
                            chartStore[key].updateOptions(options, true, true);
                            return true;
                        }

                        element.innerHTML = '';
                        chartStore[key] = new ApexCharts(element, options);
                        chartStore[key].render();
                        return true;
                    };

                    const renderCharts = (payload) => {
                        if (!payload) {
                            return false;
                        }

                        const activityRendered = renderOrUpdate('activity', '#dashboard-activity-chart', {
                            chart: {
                                type: 'line',
                                height: 320,
                                background: 'transparent',
                                toolbar: { show: false },
                                zoom: { enabled: false },
                                fontFamily: 'Instrument Sans, sans-serif',
                            },
                            theme: baseTheme(),
                            stroke: {
                                curve: 'smooth',
                                width: 3,
                            },
                            grid: {
                                borderColor: borderColor(),
                            },
                            colors: ['#0f9f6e', '#2563eb', '#f97316'],
                            series: payload.timeline.series,
                            xaxis: {
                                categories: payload.timeline.categories,
                                labels: {
                                    rotate: 0,
                                    style: {
                                        colors: payload.timeline.categories.map(() => mutedTextColor()),
                                    },
                                },
                                axisBorder: {
                                    color: borderColor(),
                                },
                                axisTicks: {
                                    color: borderColor(),
                                },
                            },
                            yaxis: {
                                min: 0,
                                forceNiceScale: true,
                                labels: {
                                    style: {
                                        colors: [mutedTextColor()],
                                    },
                                },
                            },
                            legend: {
                                position: 'top',
                                horizontalAlign: 'left',
                                labels: {
                                    colors: chartTextColor(),
                                },
                            },
                            markers: {
                                size: 4,
                                hover: { size: 6 },
                            },
                            tooltip: {
                                shared: true,
                                theme: isTamsi() ? 'dark' : 'light',
                            },
                        });

                        const copiesRendered = renderOrUpdate('copies', '#dashboard-copies-chart', {
                            chart: {
                                type: 'donut',
                                height: 220,
                                background: 'transparent',
                                toolbar: { show: false },
                                fontFamily: 'Instrument Sans, sans-serif',
                            },
                            theme: baseTheme(),
                            labels: payload.copies.labels,
                            series: payload.copies.series,
                            colors: ['#0f9f6e', '#2563eb', '#f97316', '#ef4444', '#d4d4d8', '#94a3b8'],
                            legend: {
                                show: false,
                            },
                            dataLabels: {
                                enabled: false,
                            },
                            plotOptions: {
                                pie: {
                                    donut: {
                                        size: '68%',
                                        labels: {
                                            show: true,
                                            total: {
                                                show: true,
                                                label: 'Iš viso',
                                                color: mutedTextColor(),
                                                formatter: () => payload.copies.series.reduce((sum, value) => sum + value, 0),
                                            },
                                            name: {
                                                color: mutedTextColor(),
                                            },
                                            value: {
                                                color: chartTextColor(),
                                            },
                                        },
                                    },
                                },
                            },
                            stroke: {
                                width: 0,
                            },
                        });

                        return activityRendered && copiesRendered;
                    };

                    const renderFromDom = () => {
                        const payload = payloadFromDom();

                        if (payload) {
                            window[lastPayloadKey] = payload;
                            return renderCharts(payload);
                        }

                        return false;
                    };

                    const scheduleRenderAttempts = () => {
                        [0, 60, 180, 360].forEach((delay) => {
                            window.setTimeout(() => {
                                if (!renderFromDom() && window[lastPayloadKey]) {
                                    renderCharts(window[lastPayloadKey]);
                                }
                            }, delay);
                        });
                    };

                    if (window[handlerKey]) {
                        window.removeEventListener('dashboard-charts-updated', window[handlerKey]);
                    }

                    window[handlerKey] = (event) => {
                        window[lastPayloadKey] = event.detail?.payload ?? null;
                        scheduleRenderAttempts();
                    };

                    window.addEventListener('dashboard-charts-updated', window[handlerKey]);

                    if (window[navigatedKey]) {
                        document.removeEventListener('livewire:navigated', window[navigatedKey]);
                    }

                    window[navigatedKey] = () => {
                        scheduleRenderAttempts();
                    };

                    document.addEventListener('livewire:navigated', window[navigatedKey]);

                    if (window[themeObserverKey]) {
                        window[themeObserverKey].disconnect();
                    }

                    window[themeObserverKey] = new MutationObserver((mutations) => {
                        if (mutations.some((mutation) => mutation.attributeName === 'class')) {
                            scheduleRenderAttempts();
                        }
                    });

                    window[themeObserverKey].observe(document.documentElement, {
                        attributes: true,
                        attributeFilter: ['class'],
                    });

                    scheduleRenderAttempts();
                })();
            </script>
        @endpush
    @endonce
</x-ui.page>








