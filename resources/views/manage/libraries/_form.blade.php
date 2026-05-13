@csrf

<div class="grid gap-4 lg:grid-cols-2">
    <div>
        <label for="name" class="app-label">Pavadinimas</label>
        <input id="name" type="text" name="name" value="{{ old('name', $library->name) }}" class="app-input" required>
        @error('name') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="code" class="app-label">Kodas</label>
        <input id="code" type="text" name="code" value="{{ old('code', $library->code) }}" class="app-input" required>
        @error('code') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="email" class="app-label">El. paštas</label>
        <input id="email" type="email" name="email" value="{{ old('email', $library->email) }}" class="app-input">
        @error('email') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="phone" class="app-label">Telefonas</label>
        <input id="phone" type="text" name="phone" value="{{ old('phone', $library->phone) }}" class="app-input">
        @error('phone') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div class="lg:col-span-2">
        <label for="address" class="app-label">Adresas</label>
        <input id="address" type="text" name="address" value="{{ old('address', $library->address) }}" class="app-input">
        @error('address') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="city" class="app-label">Miestas</label>
        <input id="city" type="text" name="city" value="{{ old('city', $library->city) }}" class="app-input">
        @error('city') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm font-semibold text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
            <input type="checkbox" name="is_active" value="1" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" @checked(old('is_active', $library->is_active ?? true))>
            Aktyvi
        </label>

        <label class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm font-semibold text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
            <input type="checkbox" name="is_public" value="1" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500" @checked(old('is_public', $library->is_public ?? true))>
            Vieša biblioteka
        </label>
    </div>
</div>

<div class="mt-6 flex flex-col gap-3 sm:flex-row">
    <button type="submit" class="app-button-primary">{{ $submitLabel }}</button>
    <a href="{{ route('manage.libraries.index') }}" class="app-button-secondary">Grįžti</a>
</div>







