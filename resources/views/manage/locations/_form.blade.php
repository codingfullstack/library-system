@csrf

<div class="grid gap-4 lg:grid-cols-2">
    @if(auth()->user()?->isSuperAdmin())
        <div class="lg:col-span-2">
            <label for="library_id" class="app-label">Biblioteka</label>
            <select id="library_id" name="library_id" class="app-input" required>
                <option value="">Pasirinkti biblioteką</option>
                @foreach($libraries as $library)
                    <option value="{{ $library->id }}" @selected((string) old('library_id', $location->library_id) === (string) $library->id)>
                        {{ $library->name }}
                    </option>
                @endforeach
            </select>
            @error('library_id') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
    @endif

    <div>
        <label for="branch_id" class="app-label">Filialas</label>
        <select id="branch_id" name="branch_id" class="app-input" required>
            <option value="">Pasirinkti filialą</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $location->branch_id) === (string) $branch->id)>
                    {{ $branch->name }}{{ $branch->code ? ' ('.$branch->code.')' : '' }}
                </option>
            @endforeach
        </select>
        @error('branch_id') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="name" class="app-label">Pavadinimas</label>
        <input id="name" type="text" name="name" value="{{ old('name', $location->name) }}" class="app-input" required>
        @error('name') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="code" class="app-label">Kodas</label>
        <input id="code" type="text" name="code" value="{{ old('code', $location->code) }}" class="app-input">
        @error('code') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="room" class="app-label">Kambarys</label>
        <input id="room" type="text" name="room" value="{{ old('room', $location->room) }}" class="app-input">
        @error('room') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="shelf" class="app-label">Lentyna</label>
        <input id="shelf" type="text" name="shelf" value="{{ old('shelf', $location->shelf) }}" class="app-input">
        @error('shelf') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div class="lg:col-span-2">
        <label for="description" class="app-label">Aprašymas</label>
        <textarea id="description" name="description" rows="4" class="app-input">{{ old('description', $location->description) }}</textarea>
        @error('description') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex flex-col gap-3 sm:flex-row">
    <button type="submit" class="app-button-primary">{{ $submitLabel }}</button>
    <a href="{{ route('manage.locations.index') }}" class="app-button-secondary">Grįžti</a>
</div>







