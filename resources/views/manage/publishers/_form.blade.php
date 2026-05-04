@csrf

<div class="space-y-4">
    <div>
        <label for="name" class="app-label">Pavadinimas</label>
        <input id="name" type="text" name="name" value="{{ old('name', $publisher->name) }}" class="app-input" required>
        @error('name') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="country" class="app-label">Šalis</label>
        <input id="country" type="text" name="country" value="{{ old('country', $publisher->country) }}" class="app-input">
        @error('country') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex flex-col gap-3 sm:flex-row">
    <button type="submit" class="app-button-primary">{{ $submitLabel }}</button>
    <a href="{{ route('manage.publishers.index') }}" class="app-button-secondary">Grįžti</a>
</div>
