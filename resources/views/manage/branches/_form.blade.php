@csrf

<div class="grid gap-4 lg:grid-cols-2">
    @if(auth()->user()?->isSuperAdmin())
        <div class="lg:col-span-2">
            <label for="library_id" class="app-label">Biblioteka</label>
            <select id="library_id" name="library_id" class="app-input" required>
                <option value="">Pasirinkti biblioteką</option>
                @foreach($libraries as $library)
                    <option value="{{ $library->id }}" @selected((string) old('library_id', $branch->library_id) === (string) $library->id)>
                        {{ $library->name }}
                    </option>
                @endforeach
            </select>
            @error('library_id') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
    @endif

    <div>
        <label for="name" class="app-label">Pavadinimas</label>
        <input id="name" type="text" name="name" value="{{ old('name', $branch->name) }}" class="app-input" required>
        @error('name') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="code" class="app-label">Kodas</label>
        <input id="code" type="text" name="code" value="{{ old('code', $branch->code) }}" class="app-input" required>
        @error('code') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div class="lg:col-span-2">
        <label for="address" class="app-label">Adresas</label>
        <input id="address" type="text" name="address" value="{{ old('address', $branch->address) }}" class="app-input">
        @error('address') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="city" class="app-label">Miestas</label>
        <input id="city" type="text" name="city" value="{{ old('city', $branch->city) }}" class="app-input">
        @error('city') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex flex-col gap-3 sm:flex-row">
    <button type="submit" class="app-button-primary">{{ $submitLabel }}</button>
    <a href="{{ route('manage.branches.index') }}" class="app-button-secondary">Grįžti</a>
</div>







