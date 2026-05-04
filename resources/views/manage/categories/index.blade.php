<x-layouts::app :title="'Kategorijos'">
    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        @php
            $visibleCategories = $categories->getCollection();
            $withBooks = $visibleCategories->where('books_count', '>', 0)->count();
            $withoutBooks = $visibleCategories->where('books_count', '=', 0)->count();
            $withDescription = $visibleCategories->filter(fn ($category) => filled($category->description))->count();
        @endphp

        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1500px] space-y-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">Kategorijos</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Tvarkykite bibliotekos knygu kategorijas</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('exports.list', array_merge(request()->query(), ['resource' => 'categories'])) }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.arrow-down-tray class="size-4" />
                            Eksportuoti
                        </a>
                        <a href="{{ route('manage.categories.create') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                            <flux:icon.plus class="size-4" />
                            Prideti kategorija
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

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <flux:icon.squares-2x2 class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Visos kategorijos</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $categories->total() }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Is viso</div>
                            </div>
                        </div>
                    </section>
                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">
                                <flux:icon.book-open-text class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Su knygomis</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $withBooks }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Siame puslapyje</div>
                            </div>
                        </div>
                    </section>
                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                <flux:icon.archive-box class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Be knygu</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $withoutBooks }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Siame puslapyje</div>
                            </div>
                        </div>
                    </section>
                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                                <flux:icon.document-text class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Su aprasu</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $withDescription }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Siame puslapyje</div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="px-5 py-4">
                        <form method="GET" action="{{ route('manage.categories.index') }}" class="grid gap-3 xl:grid-cols-[minmax(320px,1.5fr)_auto_auto] xl:items-center">
                            <div class="relative xl:min-w-0">
                                <input type="text" name="search" value="{{ request('search') }}" placeholder="Ieskoti kategorijos pagal pavadinima..." class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 pl-11 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400">
                                    <flux:icon.magnifying-glass class="size-4" />
                                </div>
                            </div>
                            <button type="submit" class="app-button-secondary h-11 rounded-2xl px-4">
                                <flux:icon.funnel class="mr-2 size-4" />
                                Filtruoti
                            </button>
                            <a href="{{ route('manage.categories.index') }}" class="app-button-secondary h-11 rounded-2xl px-4">Isvalyti</a>
                        </form>
                    </div>
                </section>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    @if($categories->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                <thead class="bg-zinc-50/80 dark:bg-zinc-950/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left"><input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500"></th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Kategorija</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Slug</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Aprasas</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Knygu sk.</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Busena</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Veiksmai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @foreach($categories as $category)
                                        @php
                                            $statusMeta = $category->books_count > 0
                                                ? ['label' => 'Aktyvi', 'classes' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300']
                                                : ['label' => 'Tuscia', 'classes' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'];
                                        @endphp
                                        <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                            <td class="px-4 py-4 align-middle"><input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500"></td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="font-semibold text-zinc-950 dark:text-white">{{ $category->name }}</div>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $category->slug }}</td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ \Illuminate\Support\Str::limit($category->description ?: '-', 80) }}</td>
                                            <td class="px-4 py-4 align-middle text-center text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ $category->books_count }}</td>
                                            <td class="px-4 py-4 align-middle"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusMeta['classes'] }}">{{ $statusMeta['label'] }}</span></td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex items-center gap-3 text-zinc-500 dark:text-zinc-400">
                                                    <a href="{{ route('manage.categories.edit', $category) }}" title="Redaguoti kategorija" aria-label="Redaguoti kategorija" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:text-white">
                                                        <flux:icon.pencil-square class="size-4" />
                                                    </a>
                                                    <form method="POST" action="{{ route('manage.categories.destroy', $category) }}" onsubmit="return confirm('Ar tikrai nori istrinti sia kategorija?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" title="Istrinti kategorija" aria-label="Istrinti kategorija" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white transition hover:text-red-600 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:text-red-300">
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
                            <div>Rodoma {{ $categories->firstItem() }}-{{ $categories->lastItem() }} is {{ $categories->total() }}</div>
                            <div>{{ $categories->links() }}</div>
                        </div>
                    @else
                        <div class="p-6">
                            <x-ui.empty-state title="Kategoriju nerasta" description="Pabandykite pakeisti paieska arba sukurkite nauja kategorija." />
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>
