<x-layouts::app :title="__('Knygų sąrašas')">
    <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="mb-6">
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">
                Knygų sąrašas
            </h1>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                Čia gali peržiūrėti bibliotekos knygas, ieškoti ir rūšiuoti įrašus.
            </p>
        </div>

        <div
            class="mb-6 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <form method="GET" action="{{ route('books.index') }}">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div class="xl:col-span-2">
                        <label for="search" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Paieška
                        </label>
                        <input id="search" type="text" name="search"
                            placeholder="Ieškoti pagal pavadinimą, ISBN, autorių..." value="{{ request('search') }}"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:placeholder:text-zinc-500">
                    </div>

                    <div>
                        <label for="sort" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Rūšiuoti pagal
                        </label>
                        <select id="sort" name="sort"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                            <option value="title" {{ request('sort', 'title') === 'title' ? 'selected' : '' }}>Pavadinimas
                            </option>
                            <option value="publication_year" {{ request('sort') === 'publication_year' ? 'selected' : '' }}>Leidimo metai</option>
                            <option value="copies_count" {{ request('sort') === 'copies_count' ? 'selected' : '' }}>Kopijų
                                kiekis</option>
                            <option value="created_at" {{ request('sort') === 'created_at' ? 'selected' : '' }}>Sukūrimo
                                data</option>
                        </select>
                    </div>

                    <div>
                        <label for="direction" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Kryptis
                        </label>
                        <select id="direction" name="direction"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                            <option value="asc" {{ request('direction', 'asc') === 'asc' ? 'selected' : '' }}>Didėjančiai
                            </option>
                            <option value="desc" {{ request('direction') === 'desc' ? 'selected' : '' }}>Mažėjančiai
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="per_page" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Rodyti po
                        </label>
                        <select id="per_page" name="per_page"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                            <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                            <option value="15" {{ request('per_page', 15) == 15 ? 'selected' : '' }}>15</option>
                            <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium  shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                        Filtruoti
                    </button>

                    <a href="{{ route('books.index') }}"
                        class="inline-flex items-center justify-center rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                        Išvalyti filtrus
                    </a>
                </div>
            </form>
        </div>

        @if($books->count() === 0)
            <div
                class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                    Knygų nerasta
                </h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    Pabandyk pakeisti paieškos frazę arba filtrus.
                </p>
            </div>
        @else
            <div
                class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                        <thead class="bg-zinc-50 dark:bg-zinc-950/50">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                    ID
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                    Pavadinimas
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                    ISBN
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                    Kategorija
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                    Leidykla
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                    Autoriai
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                    Metai
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                    Kopijos
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach($books as $book)
                                <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                                        #{{ $book->id }}
                                    </td>

                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-zinc-900 dark:text-white">
                                            <a href="{{ route('books.show', $book) }}"
                                                class="font-semibold text-zinc-900 transition hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">
                                                {{ $book->title }}
                                            </a>
                                        </div>

                                        @if($book->subtitle)
                                            <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                                {{ $book->subtitle }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $book->isbn ?: '—' }}
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                        <span
                                            class="inline-flex rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                            {{ $book->category?->name ?: '—' }}
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $book->publisher?->name ?: '—' }}
                                    </td>

                                    <td class="px-4 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                        @php
                                            $authors = $book->authors->pluck('name')->filter()->values();
                                        @endphp

                                        @if($authors->isNotEmpty())
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($authors as $author)
                                                    <span
                                                        class="inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                                                        {{ $author }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $book->publication_year ?: '—' }}
                                    </td>

                                    <td class="whitespace-nowrap px-4 py-4">
                                        <span
                                            class="inline-flex min-w-10 items-center justify-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                            {{ $book->copies_count }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                {{ $books->links() }}
            </div>
        @endif
    </div>
</x-layouts::app>