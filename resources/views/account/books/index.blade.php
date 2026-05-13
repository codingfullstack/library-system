<x-layouts::app :title="'Mano bibliotekų knygos'">
    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1500px] space-y-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">Mano bibliotekų knygos</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Naršyk knygas iš visų bibliotekų, prie kurių esi prisijungęs.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('public.libraries.index') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.building-library class="size-4" />
                            Viešosios bibliotekos
                        </a>
                    </div>
                </div>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="px-5 py-4">
                        <form method="GET" action="{{ route('books.index') }}" class="grid gap-3 xl:grid-cols-[minmax(320px,1.5fr)_180px_180px_180px_160px_auto_auto] xl:items-center">
                            <div class="relative xl:min-w-0">
                                <input
                                    id="search"
                                    type="text"
                                    name="search"
                                    value="{{ request('search') }}"
                                    placeholder="Ieškoti knygos pagal pavadinimą, autorių ar ISBN..."
                                    class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 pl-11 shadow-none dark:border-zinc-700 dark:bg-zinc-950"
                                >
                                <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400">
                                    <flux:icon.magnifying-glass class="size-4" />
                                </div>
                            </div>

                            <div class="xl:min-w-0">
                                <select id="category_id" name="category_id" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Kategorija</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="xl:min-w-0">
                                <select id="author_id" name="author_id" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Autorius</option>
                                    @foreach($authors as $author)
                                        <option value="{{ $author->id }}" @selected((string) request('author_id') === (string) $author->id)>
                                            {{ $author->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="xl:min-w-0">
                                <select id="publisher_id" name="publisher_id" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Leidykla</option>
                                    @foreach($publishers as $publisher)
                                        <option value="{{ $publisher->id }}" @selected((string) request('publisher_id') === (string) $publisher->id)>
                                            {{ $publisher->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="xl:min-w-0">
                                <select id="availability" name="availability" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Būsena</option>
                                    <option value="laisva" @selected(request('availability') === 'laisva')>Aktyvi</option>
                                    <option value="unavailable" @selected(request('availability') === 'unavailable')>Neprieinama</option>
                                </select>
                            </div>

                            <button type="submit" class="app-button-secondary h-11 rounded-2xl px-4 xl:w-auto">
                                <flux:icon.funnel class="mr-2 size-4" />
                                Filtruoti
                            </button>

                            <a href="{{ route('books.index') }}" class="app-button-secondary h-11 rounded-2xl px-4 xl:w-auto">
                                Išvalyti
                            </a>

                            <input type="hidden" name="sort" value="{{ request('sort', 'updated_at') }}">
                            <input type="hidden" name="direction" value="{{ request('direction', 'desc') }}">
                            <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                        </form>
                    </div>
                </section>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    @if($books->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                <thead class="bg-zinc-50/80 dark:bg-zinc-950/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Knyga</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Autorius</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Kategorija</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Biblioteka</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Būsena</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Pask. atnaujinta</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Veiksmai</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @foreach($books as $book)
                                        @php
                                            $authorsList = $book->authors->pluck('name')->filter()->values();
                                            $categoriesList = $book->categories->pluck('name')->filter()->values();
                                            $librariesList = $book->bookCopies
                                                ->pluck('library')
                                                ->filter()
                                                ->unique('id')
                                                ->map(function ($library) {
                                                    $address = collect([$library->address, $library->city])->filter()->join(', ');

                                                    return collect([$library->name, $address])->filter()->join(' · ');
                                                })
                                                ->values();
                                            $statusMeta = $book->available_copies_count > 0
                                                ? ['label' => 'Aktyvi', 'classes' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300']
                                                : ($book->loaned_copies_count > 0
                                                    ? ['label' => 'Išduota', 'classes' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300']
                                                    : ['label' => 'Neprieinama', 'classes' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300']);
                                        @endphp
                                        <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex items-start gap-3">
                                                    <div class="flex h-14 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md border border-zinc-200 bg-zinc-100 text-[10px] font-semibold uppercase text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                                        @if($book->cover_image)
                                                            <img src="{{ $book->cover_image }}" alt="{{ $book->title }}" class="h-full w-full object-cover">
                                                        @else
                                                            {{ str($book->title)->words(1, '')->substr(0, 2)->upper() }}
                                                        @endif
                                                    </div>
                                                    <div class="min-w-0">
                                                        <a href="{{ route('books.show', $book) }}" class="font-semibold text-zinc-950 transition hover:text-emerald-700 dark:text-white dark:hover:text-emerald-300">
                                                            {{ $book->title }}
                                                        </a>
                                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $book->isbn ?: '-' }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">
                                                {{ $authorsList->join(', ') ?: '-' }}
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">
                                                {{ $categoriesList->join(', ') ?: '-' }}
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">
                                                {{ $librariesList->join(', ') ?: '-' }}
                                            </td>
                                            <td class="px-4 py-4 align-middle">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusMeta['classes'] }}">
                                                    {{ $statusMeta['label'] }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-600 dark:text-zinc-400">
                                                {{ $book->updated_at?->format('Y-m-d') ?: '-' }}
                                            </td>
                                            <td class="px-4 py-4 align-middle">
                                                <a href="{{ route('books.show', $book) }}" title="Peržiūrėti knygą" aria-label="Peržiūrėti knygą" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-500 transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                                    <flux:icon.eye class="size-4" />
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-zinc-200 px-5 py-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
                            <div>Rodoma {{ $books->firstItem() }}-{{ $books->lastItem() }} iš {{ $books->total() }}</div>
                            <div class="books-pagination">{{ $books->links() }}</div>
                        </div>
                    @else
                        <div class="p-6">
                            <x-ui.empty-state
                                title="Knygų nerasta"
                                description="Pabandyk pakeisti paiešką arba filtrus."
                            />
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>







