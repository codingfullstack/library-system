<x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
    <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
        <div class="mx-auto max-w-[1500px] space-y-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">Pridėti egzempliorių</h1>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Raskite bendro katalogo knygą ir pridėkite savo bibliotekos fizinę kopiją.</p>
                </div>

                <a href="{{ route('manage.book-copies.index') }}" wire:navigate class="inline-flex h-11 items-center gap-2 self-start rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                    <flux:icon.squares-plus class="size-4" />
                    Visi egzemplioriai
                </a>
            </div>

            @if(session('success'))
                <x-ui.alert>{{ session('success') }}</x-ui.alert>
            @endif

            @if(session('error'))
                <x-ui.alert type="error">{{ session('error') }}</x-ui.alert>
            @endif

            <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Knygos paieška</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Ieškok bendrame kataloge pagal pavadinimą, ISBN, autorių ar leidyklą.</p>
                </div>

                <div class="grid gap-3 px-5 py-4 xl:grid-cols-[minmax(320px,1.5fr)_220px] xl:items-center">
                    <div class="relative xl:min-w-0">
                        <input id="search" type="text" wire:model.live.debounce.350ms="search" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 pl-11 shadow-none dark:border-zinc-700 dark:bg-zinc-950" placeholder="Pavadinimas, ISBN, autorius arba leidykla">
                        <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400">
                            <flux:icon.magnifying-glass class="size-4" />
                        </div>
                    </div>

                    @if(auth()->user()?->isSuperAdmin())
                        <select id="library_id" wire:model.live="selectedLibraryId" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                            <option value="">Biblioteka</option>
                            @foreach($libraries as $library)
                                <option value="{{ $library->id }}">{{ $library->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </section>

            <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Bendras katalogas</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Pasirinkite esamą knygą ir pridėkite savo bibliotekos kopiją.</p>
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
                                            <a href="{{ route('books.show', $book) }}" class="font-semibold text-zinc-950 transition hover:text-emerald-700 dark:text-white dark:hover:text-emerald-300">
                                                {{ $book->title }}
                                            </a>
                                            @if($book->subtitle)
                                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $book->subtitle }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $book->isbn ?: '-' }}</td>
                                        <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $book->publisher?->name ?: '-' }}</td>
                                        <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $book->authors->pluck('name')->join(', ') ?: '-' }}</td>
                                        <td class="px-4 py-4 align-middle">
                                            <div class="flex justify-end">
                                                <button type="button" wire:click="selectBook({{ $book->id }})" class="app-button-secondary h-11 rounded-2xl px-4">Pasirinkti</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        {{ $books->links() }}
                    </div>
                @else
                    <div class="p-5">
                        <x-ui.empty-state title="Knygų nerasta" description="Pabandyk pakeisti paiešką arba sukurk naują knygos įrašą." />
                    </div>
                @endif
            </section>
        </div>
    </div>

    @if($selectedBook)
        <div class="fixed inset-0 z-50 bg-zinc-950/50" wire:key="create-book-copy-drawer-{{ $selectedBook->id }}">
            <button type="button" wire:click="closeDrawer" class="absolute inset-0 cursor-default" aria-label="Uždaryti"></button>

            <aside class="absolute inset-y-0 right-0 z-10 flex h-full w-96 max-w-[calc(100vw-1rem)] flex-col overflow-hidden border-l border-zinc-200 bg-white shadow-2xl sm:w-[32rem] dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4 border-b border-zinc-200 px-6 py-5 dark:border-zinc-800">
                    <div>
                        <h2 class="text-xl font-bold text-zinc-950 dark:text-white">Pridėti egzempliorių</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Nauja kopija bus pridėta prie pasirinktos knygos.</p>
                    </div>

                    <button type="button" wire:click="closeDrawer" aria-label="Uždaryti" class="inline-flex size-10 shrink-0 items-center justify-center rounded-2xl border border-zinc-200 bg-white text-zinc-500 shadow-sm transition hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white">
                        <flux:icon.x-mark class="size-5" />
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    <livewire:manage.book-copies.book-copy-form
                        :selected-book="$selectedBook"
                        :selected-library-id="$selectedLibraryId"
                        :drawer-mode="true"
                        :key="'manage-book-copy-create-'.$selectedBook->id.'-'.$selectedLibraryId"
                    />
                </div>
            </aside>
        </div>
    @endif
</x-ui.page>
