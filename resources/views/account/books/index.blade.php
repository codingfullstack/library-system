<x-layouts::app :title="'Bibliotekos knygos'">
    <x-ui.page>
        <x-ui.page-header
            eyebrow="Katalogas"
            title="Bibliotekos knygos"
            description="Naršyk savo bibliotekos knygas, pilnus aprasymus ir prieinamuma."
        />

        <x-ui.panel class="mb-6" title="Paieska ir filtrai" description="Greitai rask dominancia knyga kataloge.">
            <form method="GET" action="{{ route('books.index') }}">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div class="md:col-span-2">
                        <label for="search" class="app-label">Paieska</label>
                        <input id="search" type="text" name="search" value="{{ request('search') }}" placeholder="Pavadinimas, autorius, ISBN..." class="app-input">
                    </div>

                    <div>
                        <label for="author_id" class="app-label">Autorius</label>
                        <select id="author_id" name="author_id" class="app-input">
                            <option value="">Visi</option>
                            @foreach($authors as $author)
                                <option value="{{ $author->id }}" @selected((string) request('author_id') === (string) $author->id)>{{ $author->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="category_id" class="app-label">Kategorija</label>
                        <select id="category_id" name="category_id" class="app-input">
                            <option value="">Visos</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="publisher_id" class="app-label">Leidykla</label>
                        <select id="publisher_id" name="publisher_id" class="app-input">
                            <option value="">Visos</option>
                            @foreach($publishers as $publisher)
                                <option value="{{ $publisher->id }}" @selected((string) request('publisher_id') === (string) $publisher->id)>{{ $publisher->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="availability" class="app-label">Prieinamumas</label>
                        <select id="availability" name="availability" class="app-input">
                            <option value="">Visos</option>
                            <option value="available" @selected(request('availability') === 'available')>Yra laisvu</option>
                            <option value="unavailable" @selected(request('availability') === 'unavailable')>Laisvu kopiju nera</option>
                        </select>
                    </div>

                    <div>
                        <label for="sort" class="app-label">Rikiuoti</label>
                        <select id="sort" name="sort" class="app-input">
                            <option value="title" @selected(request('sort', 'title') === 'title')>Pavadinimas</option>
                            <option value="publication_year" @selected(request('sort') === 'publication_year')>Metai</option>
                            <option value="copies_count" @selected(request('sort') === 'copies_count')>Kopiju kiekis</option>
                            <option value="created_at" @selected(request('sort') === 'created_at')>Naujausios</option>
                        </select>
                    </div>

                    <div>
                        <label for="direction" class="app-label">Tvarka</label>
                        <select id="direction" name="direction" class="app-input">
                            <option value="asc" @selected(request('direction', 'asc') === 'asc')>Didejanti</option>
                            <option value="desc" @selected(request('direction') === 'desc')>Mazejanti</option>
                        </select>
                    </div>

                    <div>
                        <label for="per_page" class="app-label">Rodyti po</label>
                        <select id="per_page" name="per_page" class="app-input">
                            <option value="12" @selected(request('per_page') == 12)>12</option>
                            <option value="15" @selected(request('per_page', 15) == 15)>15</option>
                            <option value="24" @selected(request('per_page') == 24)>24</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <button type="submit" class="app-button-primary">Filtruoti</button>
                    <a href="{{ route('books.index') }}" class="app-button-secondary">Isvalyti</a>
                </div>
            </form>
        </x-ui.panel>

        @if($books->count())
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($books as $book)
                    <article class="app-card flex h-full flex-col justify-between">
                        <div>
                            <a href="{{ route('books.show', $book) }}" class="text-xl font-semibold text-zinc-950 hover:text-teal-700 dark:text-white dark:hover:text-teal-300">
                                {{ $book->title }}
                            </a>
                            @if($book->subtitle)
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $book->subtitle }}</p>
                            @endif

                            <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $book->authors->pluck('name')->join(', ') ?: 'Autorius nenurodytas' }}
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                @foreach($book->categories as $category)
                                    <span class="rounded-full bg-zinc-100 px-2.5 py-1 dark:bg-zinc-800">{{ $category->name }}</span>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-5 space-y-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="app-muted-card">
                                    <div class="app-label">Kopijos</div>
                                    <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $book->copies_count }}</div>
                                </div>
                                <div class="app-muted-card">
                                    <div class="app-label">Laisvos</div>
                                    <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $book->available_copies_count }}</div>
                                </div>
                            </div>

                            <a href="{{ route('books.show', $book) }}" class="app-button-secondary w-full">Placiau</a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $books->links() }}
            </div>
        @else
            <x-ui.panel>
                <x-ui.empty-state title="Knygu nerasta" description="Pabandyk pakeisti paieska arba filtrus." />
            </x-ui.panel>
        @endif
    </x-ui.page>
</x-layouts::app>
