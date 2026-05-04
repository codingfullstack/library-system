<x-layouts::app :title="'Prideti egzemplioriu'">
    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1500px] space-y-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">Prideti egzemplioriu</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Rask bendro katalogo knyga ir pridek savo bibliotekos fizine kopija.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('manage.book-copies.index') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.squares-plus class="size-4" />
                            Visi egzemplioriai
                        </a>
                    </div>
                </div>

                @if(session('success'))
                    <x-ui.alert>{{ session('success') }}</x-ui.alert>
                @endif

                @if(session('error'))
                    <x-ui.alert type="error">{{ session('error') }}</x-ui.alert>
                @endif

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Knygos paieska</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Ieskok bendrame kataloge pagal pavadinima, ISBN, autoriu ar leidykla.</p>
                    </div>

                    <div class="px-5 py-4">
                        <form method="GET" action="{{ route('manage.book-copies.create') }}" class="grid gap-3 xl:grid-cols-[minmax(320px,1.5fr)_220px_auto_auto] xl:items-center">
                            <div class="relative xl:min-w-0">
                                <input id="search" type="text" name="search" value="{{ request('search') }}" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 pl-11 shadow-none dark:border-zinc-700 dark:bg-zinc-950" placeholder="Pavadinimas, ISBN, autorius arba leidykla">
                                <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400">
                                    <flux:icon.magnifying-glass class="size-4" />
                                </div>
                            </div>

                            @if(auth()->user()?->isSuperAdmin())
                                <div class="xl:min-w-0">
                                    <select id="library_id" name="library_id" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                        <option value="">Biblioteka</option>
                                        @foreach($libraries as $library)
                                            <option value="{{ $library->id }}" @selected((string) $selectedLibraryId === (string) $library->id)>{{ $library->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <button type="submit" class="app-button-secondary h-11 rounded-2xl px-4">
                                <flux:icon.funnel class="mr-2 size-4" />
                                Ieskoti
                            </button>

                            <a href="{{ route('manage.book-copies.create') }}" class="app-button-secondary h-11 rounded-2xl px-4">
                                Isvalyti
                            </a>
                        </form>
                    </div>
                </section>

                @if($selectedBook)
                    <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Nauja kopija</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Pridedi nauja fizine kopija knygai: {{ $selectedBook->title }}</p>
                        </div>

                        <div class="px-5 py-5">
                            <div class="mb-6 rounded-[22px] border border-emerald-200 bg-emerald-50/70 p-5 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $selectedBook->title }}</p>
                                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                            {{ $selectedBook->authors->pluck('name')->join(', ') ?: 'Autorius nenurodytas' }}
                                        </p>
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            ISBN: {{ $selectedBook->isbn ?: '-' }}
                                            @if($selectedBook->publisher)
                                                - Leidykla: {{ $selectedBook->publisher->name }}
                                            @endif
                                            @if($selectedBook->categories?->isNotEmpty())
                                                - Kategorijos: {{ $selectedBook->categories->pluck('name')->join(', ') }}
                                            @endif
                                        </p>
                                    </div>

                                    <a
                                        href="{{ route('manage.book-copies.create', array_filter(['search' => request('search'), 'library_id' => $selectedLibraryId])) }}"
                                        class="inline-flex h-10 items-center justify-center rounded-xl border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-600 transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:text-white"
                                    >
                                        Atsaukti pasirinkima
                                    </a>
                                </div>
                            </div>

                            <livewire:manage.book-copies.book-copy-form
                                :selected-book="$selectedBook"
                                :selected-library-id="$selectedLibraryId"
                                :key="'manage-book-copy-create-'.$selectedBook->id.'-'.$selectedLibraryId"
                            />
                        </div>
                    </section>
                @endif

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Bendras katalogas</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Pasirink esama knyga ir pridek savo bibliotekos kopija.</p>
                    </div>

                    @if($books->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                <thead class="bg-zinc-50/80 dark:bg-zinc-950/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Knyga</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">ISBN</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Leidykla</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Autoriai</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Veiksmas</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @foreach($books as $book)
                                        <tr class="transition hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40">
                                            <td class="px-4 py-4 align-middle">
                                                <div class="font-semibold text-zinc-950 dark:text-white">{{ $book->title }}</div>
                                                @if($book->subtitle)
                                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $book->subtitle }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $book->isbn ?: '-' }}</td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $book->publisher?->name ?: '-' }}</td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $book->authors->pluck('name')->join(', ') ?: '-' }}</td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex justify-end">
                                                    <a
                                                        href="{{ route('manage.book-copies.create', array_filter(['search' => request('search'), 'library_id' => $selectedLibraryId, 'book_id' => $book->id])) }}"
                                                        class="app-button-secondary h-11 rounded-2xl px-4"
                                                    >
                                                        Pasirinkti
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-5">
                            <x-ui.empty-state title="Knygu nerasta" description="Pabandyk pakeisti paieska arba sukurk nauja knygos irasa." />
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>
