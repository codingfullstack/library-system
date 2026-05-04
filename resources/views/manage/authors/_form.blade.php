@csrf

<div class="space-y-4">
    <div>
        <label for="name" class="app-label">Vardas</label>
        <input id="name" type="text" name="name" value="{{ old('name', $author->name) }}" class="app-input" required>
        @error('name') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="bio" class="app-label">Biografija</label>
        <textarea id="bio" name="bio" rows="6" class="app-input">{{ old('bio', $author->bio) }}</textarea>
        @error('bio') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex flex-col gap-3 sm:flex-row">
    <button type="submit" class="app-button-primary">{{ $submitLabel }}</button>
    <a href="{{ route('manage.authors.index') }}" class="app-button-secondary">Grįžti</a>
</div>
