<x-layouts::app :title="'Filialai'">
    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        @php
            $visibleBranches = $branches->getCollection();
            $branchesWithLocations = $visibleBranches->where('locations_count', '>', 0)->count();
            $branchesWithCopies = $visibleBranches->where('book_copies_count', '>', 0)->count();
            $citiesCount = $visibleBranches->pluck('city')->filter()->unique()->count();
        @endphp

        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1500px] space-y-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">Filialai</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Tvarkykite bibliotekos filialus</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('exports.list', array_merge(request()->query(), ['resource' => 'branches'])) }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.arrow-down-tray class="size-4" />
                            Eksportuoti
                        </a>

                        <a href="{{ route('manage.imports.show', 'branches') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.arrow-up-tray class="size-4" />
                            Importuoti
                        </a>

                        <a href="{{ route('manage.branches.create') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                            <flux:icon.plus class="size-4" />
                            Pridėti filialą
                            <flux:icon.chevron-down class="size-4" />
                        </a>
                    </div>
                </div>

                @if(session('success'))
                    <x-ui.alert>{{ session('success') }}</x-ui.alert>
                @endif

                @if(session('error'))
                    <x-ui.alert type="error">{{ session('error') }}</x-ui.alert>
                @endif

                @include('manage.imports._result')

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <flux:icon.building-office-2 class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Visi filialai</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $branches->total() }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Iš viso</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">
                                <flux:icon.map-pin class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Su vietomis</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $branchesWithLocations }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Šiame puslapyje</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                <flux:icon.book-open-text class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Su egzemploriais</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $branchesWithCopies }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Aktyvus fondas</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                                <flux:icon.map class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Miestai</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $citiesCount }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Šiame puslapyje</div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="px-5 py-4">
                        <form method="GET" action="{{ route('manage.branches.index') }}" class="grid gap-3 xl:grid-cols-[minmax(320px,1.5fr)_auto_auto] xl:items-center">
                            <div class="relative xl:min-w-0">
                                <input type="text" name="search" value="{{ request('search') }}" placeholder="Ieškoti pagal pavadinimą, kodą ar miestą..." class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 pl-11 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400">
                                    <flux:icon.magnifying-glass class="size-4" />
                                </div>
                            </div>
                            <button type="submit" class="app-button-secondary h-11 rounded-2xl px-4">
                                <flux:icon.funnel class="mr-2 size-4" />
                                Filtruoti
                            </button>
                            <a href="{{ route('manage.branches.index') }}" class="app-button-secondary h-11 rounded-2xl px-4">Išvalyti</a>
                        </form>
                    </div>
                </section>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    @if($branches->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                <thead class="bg-zinc-50/80 dark:bg-zinc-950/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left"><input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500"></th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Filialas</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Kodas</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Biblioteka</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Miestas</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Vietos</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Egz.</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Veiksmai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @foreach($branches as $branch)
                                        <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                            <td class="px-4 py-4 align-middle"><input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500"></td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="font-semibold text-zinc-950 dark:text-white">{{ $branch->name }}</div>
                                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $branch->address ?: '-' }}</div>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $branch->code }}</td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $branch->library?->name ?: '-' }}</td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $branch->city ?: '-' }}</td>
                                            <td class="px-4 py-4 align-middle text-center text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $branch->locations_count }}</td>
                                            <td class="px-4 py-4 align-middle text-center text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ $branch->book_copies_count }}</td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex items-center gap-3 text-zinc-500 dark:text-zinc-400">
                                                    <a href="{{ route('manage.branches.edit', $branch) }}" title="Redaguoti filialą" aria-label="Redaguoti filialą" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:text-white">
                                                        <flux:icon.pencil-square class="size-4" />
                                                    </a>
                                                    <form method="POST" action="{{ route('manage.branches.destroy', $branch) }}" onsubmit="return confirm('Ar tikrai nori ištrinti šį filialą?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" title="Ištrinti filialą" aria-label="Ištrinti filialą" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white transition hover:text-red-600 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:text-red-300">
                                                            <flux:icon.trash class="size-4" />
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-zinc-200 px-5 py-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
                            <div>Rodoma {{ $branches->firstItem() }}-{{ $branches->lastItem() }} is {{ $branches->total() }}</div>
                            <div>{{ $branches->links() }}</div>
                        </div>
                    @else
                        <div class="p-6">
                            <x-ui.empty-state title="Filialų nerasta" description="Pabandykite pakeisti paiešką arba sukurkite naują filialą." />
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>







