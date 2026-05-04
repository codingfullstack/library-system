@csrf

<div class="space-y-4">
    <div>
        <label for="name" class="app-label">Pavadinimas</label>
        <input id="name" type="text" name="name" value="{{ old('name', $category->name) }}" class="app-input" required>
        @error('name') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="slug" class="app-label">Slug</label>
        <input id="slug" type="text" name="slug" value="{{ old('slug', $category->slug) }}" class="app-input" placeholder="Palik tuščią, sugeneruosime automatiškai">
        @error('slug') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="description" class="app-label">Aprašymas</label>
        <textarea id="description" name="description" rows="5" class="app-input">{{ old('description', $category->description) }}</textarea>
        @error('description') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex flex-col gap-3 sm:flex-row">
    <button type="submit" class="app-button-primary">{{ $submitLabel }}</button>
    <a href="{{ route('manage.categories.index') }}" class="app-button-secondary">Grįžti</a>
</div>
