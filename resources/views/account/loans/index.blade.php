<x-layouts::app :title="'Mano išduotos knygos'">
    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1500px] space-y-7">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">Mano išduotos knygos</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Visos šiuo metu tavo paimtos knygos ir jų grąžinimo terminai.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('books.index') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.book-open class="size-4" />
                            Katalogas
                        </a>
                    </div>
                </div>

                <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-[22px] border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-4">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <flux:icon.book-open class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Visos išduotos</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['all_count'] ?? 0 }}</div>
                                <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Šiuo metu</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[22px] border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-4">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">
                                <flux:icon.check-circle class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Aktyvios</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['active_count'] ?? 0 }}</div>
                                <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Nevėluoja</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[22px] border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-4">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300">
                                <flux:icon.exclamation-circle class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Vėluojančios</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['overdue_count'] ?? 0 }}</div>
                                <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Reikia grąžinti</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[22px] border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-4">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                <flux:icon.clock class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Greitai grąžinti</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['due_soon_count'] ?? 0 }}</div>
                                <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Per 7 dienas</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[22px] border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-4">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-purple-100 text-purple-700 dark:bg-purple-500/15 dark:text-purple-300">
                                <flux:icon.calendar-days class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Be termino</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['no_due_date_count'] ?? 0 }}</div>
                                <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Terminas nepriskirtas</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="px-5 py-4">
                        <form method="GET" action="{{ route('loans.index') }}" class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center">
                            <div class="relative lg:min-w-[320px] lg:flex-1">
                                <input id="search" type="text" name="search" value="{{ request('search') }}" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 pl-11 shadow-none dark:border-zinc-700 dark:bg-zinc-950" placeholder="Ieškoti pagal knygą, ISBN ar kopiją...">
                                <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400">
                                    <flux:icon.magnifying-glass class="size-4" />
                                </div>
                            </div>

                            <select id="status" name="status" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none lg:w-48 dark:border-zinc-700 dark:bg-zinc-950">
                                <option value="">Visos aktyvios</option>
                                <option value="aktyvi" @selected(request('status') === 'aktyvi')>Aktyvios</option>
                                <option value="vėluoja" @selected(request('status') === 'vėluoja')>Vėluojančios</option>
                            </select>

                            <select id="per_page" name="per_page" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none lg:w-28 dark:border-zinc-700 dark:bg-zinc-950">
                                <option value="10" @selected(request('per_page') == 10)>10</option>
                                <option value="15" @selected(request('per_page', 15) == 15)>15</option>
                                <option value="25" @selected(request('per_page') == 25)>25</option>
                            </select>

                            <div class="flex flex-col gap-3 sm:flex-row lg:w-auto lg:shrink-0">
                            <button type="submit" class="app-button-secondary h-11 rounded-2xl px-4">
                                <flux:icon.funnel class="mr-2 size-4" />
                                Filtruoti
                            </button>
                            <a href="{{ route('loans.index') }}" class="app-button-secondary h-11 rounded-2xl px-4">Išvalyti</a>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    @if($loans->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                <thead class="bg-zinc-50/80 dark:bg-zinc-950/50">
                                    <tr>
                                        <th class="w-12 px-5 py-4 text-left">
                                            <input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-900">
                                        </th>
                                        <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Knyga</th>
                                        <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Biblioteka</th>
                                        <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Kopija</th>
                                        <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Paimta</th>
                                        <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Grąžinti iki</th>
                                        <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Būsena</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @foreach($loans as $loan)
                                        @php($book = $loan->bookCopy?->book)
                                        <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                            <td class="px-5 py-4 align-middle">
                                                <input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-900">
                                            </td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex items-center gap-4">
                                                    <div class="flex h-[70px] w-[50px] shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-zinc-100 text-xs font-semibold uppercase text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                                        {{ str($book?->title ?? 'Knyga')->words(1, '')->substr(0, 2)->upper() }}
                                                    </div>
                                                    <div class="min-w-[220px]">
                                                        <a href="{{ $book ? route('books.show', $book) : '#' }}" class="font-semibold text-zinc-950 transition hover:text-emerald-700 dark:text-white dark:hover:text-emerald-300">
                                                            {{ $book?->title ?: 'Nežinoma knyga' }}
                                                        </a>
                                                        <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">ISBN: {{ $book?->isbn ?: '-' }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $loan->library?->name ?: '-' }}</td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $loan->bookCopy?->inventory_code ?: '-' }}</td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $loan->borrowed_at?->format('Y-m-d H:i') ?: '-' }}</td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $loan->due_at?->format('Y-m-d') ?: 'Be termino' }}</td>
                                            <td class="px-4 py-4 align-middle">
                                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $loan->is_overdue ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' }}">
                                                    {{ $loan->is_overdue ? 'Vėluoja '.$loan->overdue_days.' d.' : 'Aktyvi' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-zinc-200 px-5 py-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
                            <div>Rodoma {{ $loans->firstItem() }}-{{ $loans->lastItem() }} iš {{ $loans->total() }}</div>
                            <div>{{ $loans->links() }}</div>
                        </div>
                    @else
                        <div class="p-6">
                            <x-ui.empty-state title="Aktyvių išdavimų nėra" description="Kai pasiimsi knygą, ji atsiras šiame sąraše." />
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>







