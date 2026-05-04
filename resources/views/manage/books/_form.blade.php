@csrf

@php
    $selectedAuthors = $authors
        ->whereIn('id', collect(old('author_ids', $book->authors?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id))
        ->map(fn ($author) => ['id' => $author->id, 'name' => $author->name])
        ->values();

    $selectedCategories = $categories
        ->whereIn('id', collect(old('category_ids', $book->categories?->pluck('id')->all() ?? ($book->category_id ? [$book->category_id] : [])))->map(fn ($id) => (int) $id))
        ->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])
        ->values();
@endphp

<div class="grid gap-4 lg:grid-cols-2">
    <div class="lg:col-span-2">
        <label for="title" class="app-label">Pavadinimas</label>
        <input id="title" type="text" name="title" value="{{ old('title', $book->title) }}" class="app-input" required>
        @error('title') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div class="lg:col-span-2">
        <label for="subtitle" class="app-label">Paantraštė</label>
        <input id="subtitle" type="text" name="subtitle" value="{{ old('subtitle', $book->subtitle) }}" class="app-input">
        @error('subtitle') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="isbn" class="app-label">ISBN</label>
        <input id="isbn" type="text" name="isbn" value="{{ old('isbn', $book->isbn) }}" class="app-input">
        @error('isbn') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="publication_year" class="app-label">Leidimo metai</label>
        <input id="publication_year" type="number" name="publication_year" value="{{ old('publication_year', $book->publication_year) }}" class="app-input">
        @error('publication_year') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div class="lg:col-span-2">
        <label for="category-selector" class="app-label">Kategorijos</label>

        <div
            class="space-y-3"
            data-multi-picker
            data-initial-items='@json($selectedCategories)'
            data-input-name="category_ids[]"
            data-empty-text="Kategorijų dar nepasirinkta."
        >
            <div class="flex flex-col gap-3 sm:flex-row">
                <select id="category-selector" class="app-input" data-picker-select>
                    <option value="">Pasirink kategoriją</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>

                <button type="button" class="app-button-secondary sm:shrink-0" data-picker-add>
                    Pridėti kategoriją
                </button>
            </div>

            <div class="app-selection-list" data-picker-list></div>
            <div data-picker-hidden-inputs></div>

            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Knygai gali priskirti kelias kategorijas. Paspaudus `x`, kategoriją galėsi pašalinti.
            </p>
        </div>

        @error('category_ids') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        @error('category_ids.*') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="publisher_id" class="app-label">Leidykla</label>
        <select id="publisher_id" name="publisher_id" class="app-input">
            <option value="">Pasirinkti</option>
            @foreach($publishers as $publisher)
                <option value="{{ $publisher->id }}" @selected((string) old('publisher_id', $book->publisher_id) === (string) $publisher->id)>
                    {{ $publisher->name }}
                </option>
            @endforeach
        </select>
        @error('publisher_id') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="language" class="app-label">Kalba</label>
        <input id="language" type="text" name="language" value="{{ old('language', $book->language) }}" class="app-input">
        @error('language') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="page_count" class="app-label">Puslapių skaičius</label>
        <input id="page_count" type="number" name="page_count" value="{{ old('page_count', $book->page_count) }}" class="app-input">
        @error('page_count') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="edition" class="app-label">Leidimas</label>
        <input id="edition" type="text" name="edition" value="{{ old('edition', $book->edition) }}" class="app-input">
        @error('edition') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="cover_image" class="app-label">Viršelio nuoroda</label>
        <input id="cover_image" type="text" name="cover_image" value="{{ old('cover_image', $book->cover_image) }}" class="app-input">
        @error('cover_image') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div class="lg:col-span-2">
        <label for="author-selector" class="app-label">Autoriai</label>

        <div
            class="space-y-3"
            data-multi-picker
            data-initial-items='@json($selectedAuthors)'
            data-input-name="author_ids[]"
            data-empty-text="Autorių dar nepasirinkta."
        >
            <div class="flex flex-col gap-3 sm:flex-row">
                <select id="author-selector" class="app-input" data-picker-select>
                    <option value="">Pasirink autorių</option>
                    @foreach($authors as $author)
                        <option value="{{ $author->id }}">{{ $author->name }}</option>
                    @endforeach
                </select>

                <button type="button" class="app-button-secondary sm:shrink-0" data-picker-add>
                    Pridėti autorių
                </button>
            </div>

            <div class="app-selection-list" data-picker-list></div>
            <div data-picker-hidden-inputs></div>

            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Pasirink autoriaus vardą, jis atsiras žemiau. Paspaudus `x`, autorių galėsi pašalinti.
            </p>
        </div>

        @error('author_ids') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        @error('author_ids.*') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div class="lg:col-span-2">
        <label for="description" class="app-label">Aprašymas</label>
        <textarea id="description" name="description" rows="5" class="app-input">{{ old('description', $book->description) }}</textarea>
        @error('description') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex flex-col gap-3 sm:flex-row">
    <button type="submit" class="app-button-primary">{{ $submitLabel }}</button>
    <a href="{{ route('books.index') }}" class="app-button-secondary">Grįžti</a>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-multi-picker]').forEach((picker) => {
                    const select = picker.querySelector('[data-picker-select]');
                    const addButton = picker.querySelector('[data-picker-add]');
                    const list = picker.querySelector('[data-picker-list]');
                    const hiddenInputs = picker.querySelector('[data-picker-hidden-inputs]');
                    const inputName = picker.dataset.inputName;
                    const emptyText = picker.dataset.emptyText || 'Nėra pasirinktų reikšmių.';
                    const initialItems = JSON.parse(picker.dataset.initialItems || '[]');
                    const selectedItems = new Map();

                    const render = () => {
                        list.innerHTML = '';
                        hiddenInputs.innerHTML = '';

                        if (selectedItems.size === 0) {
                            const empty = document.createElement('p');
                            empty.className = 'text-sm text-zinc-500 dark:text-zinc-400';
                            empty.textContent = emptyText;
                            list.appendChild(empty);
                            return;
                        }

                        selectedItems.forEach((name, id) => {
                            const chip = document.createElement('div');
                            chip.className = 'app-selection-chip';

                            const label = document.createElement('span');
                            label.textContent = name;
                            chip.appendChild(label);

                            const remove = document.createElement('button');
                            remove.type = 'button';
                            remove.className = 'app-selection-chip-remove';
                            remove.textContent = 'x';
                            remove.setAttribute('aria-label', `Pašalinti ${name}`);
                            remove.addEventListener('click', () => {
                                selectedItems.delete(id);
                                render();
                            });
                            chip.appendChild(remove);

                            list.appendChild(chip);

                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = inputName;
                            input.value = id;
                            hiddenInputs.appendChild(input);
                        });
                    };

                    const addSelectedItem = () => {
                        const option = select.options[select.selectedIndex];

                        if (! option || ! option.value) {
                            return;
                        }

                        if (! selectedItems.has(option.value)) {
                            selectedItems.set(option.value, option.text.trim());
                        }

                        select.value = '';
                        render();
                    };

                    initialItems.forEach((item) => {
                        if (item && item.id && item.name) {
                            selectedItems.set(String(item.id), item.name);
                        }
                    });

                    addButton.addEventListener('click', addSelectedItem);
                    select.addEventListener('change', addSelectedItem);

                    render();
                });
            });
        </script>
    @endpush
@endonce

