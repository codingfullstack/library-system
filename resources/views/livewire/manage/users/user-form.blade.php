@php
    $actor = auth()->user();
    $roleLabels = [
        'superadministratorius' => 'Superadministratorius',
        'administratorius' => 'Administratorius',
        'darbuotojas' => 'Darbuotojas',
        'narys' => 'Narys',
    ];
@endphp

<form wire:submit="save" class="space-y-6">
    <div class="grid gap-4 lg:grid-cols-2">
        <div>
            <label for="name" class="app-label">Vardas</label>
            <input id="name" type="text" wire:model="name" class="app-input" required>
            @error('name') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="app-label">El. paštas</label>
            <input id="email" type="email" wire:model="email" class="app-input" required>
            @error('email') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="role" class="app-label">Rolė</label>
            <select id="role" wire:model.live="role" class="app-input" required>
                @foreach($roleOptions as $option)
                    <option value="{{ $option }}">{{ $roleLabels[$option] ?? $option }}</option>
                @endforeach
            </select>
            @error('role') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        @if($actor?->isSuperAdmin())
            <div>
                <label for="libraryId" class="app-label">Biblioteka</label>
                <select id="libraryId" wire:model.live="libraryId" class="app-input" @disabled($role === 'superadministratorius')>
                    <option value="">{{ $role === 'superadministratorius' ? 'Be bibliotekos' : 'Pasirinkti biblioteką' }}</option>
                    @foreach($libraries as $library)
                        <option value="{{ $library->id }}">
                            {{ $library->name }}{{ $library->code ? ' ('.$library->code.')' : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $role === 'superadministratorius' ? 'Superadministratoriui biblioteka nepriskiriama.' : 'Nario rolei nario numeris generuojamas automatiškai.' }}
                </p>
                @error('libraryId') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        @else
            <div>
                <label class="app-label">Biblioteka</label>
                <div class="app-input flex items-center bg-zinc-50 text-zinc-600 dark:bg-zinc-950/40 dark:text-zinc-300">
                    {{ $actor?->library?->name ?: '-' }}
                </div>
            </div>
        @endif

        <div>
            <label for="phone" class="app-label">Telefonas</label>
            <input id="phone" type="text" wire:model="phone" class="app-input">
            @error('phone') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="app-label">Nario numeris</label>
            <div class="app-input flex items-center bg-zinc-50 text-zinc-600 dark:bg-zinc-950/40 dark:text-zinc-300">
                {{ $previewMembershipNumber ?: ($role === 'narys' ? 'Pasirinkite biblioteką, kad sugeneruotumėte' : 'Netaikoma') }}
            </div>
        </div>

        <div>
            <label for="password" class="app-label">{{ $isEditing ? 'Naujas slaptažodis' : 'Slaptažodis' }}</label>
            <input id="password" type="password" wire:model="password" class="app-input" {{ $isEditing ? '' : 'required' }}>
            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                {{ $isEditing ? 'Palik tusčia, jei keisti nereikia.' : 'Bent 8 simboliai.' }}
            </p>
            @error('password') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="passwordConfirmation" class="app-label">Pakartok slaptažodį</label>
            <input id="passwordConfirmation" type="password" wire:model="passwordConfirmation" class="app-input" {{ $isEditing ? '' : 'required' }}>
            @error('passwordConfirmation') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="lg:col-span-2">
            <label class="flex items-center gap-3 text-sm text-zinc-700 dark:text-zinc-300">
                <input
                    type="checkbox"
                    wire:model="isActive"
                    class="rounded border-zinc-300 text-teal-700 focus:ring-teal-600 dark:border-zinc-700 dark:bg-zinc-900"
                >
                Aktyvus vartotojas
            </label>
            @error('isActive') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <button type="submit" class="app-button-primary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">{{ $isEditing ? 'Išsaugoti pakeitimus' : 'Sukurti vartotoją' }}</span>
            <span wire:loading wire:target="save">{{ $isEditing ? 'Saugoma...' : 'Kuriama...' }}</span>
        </button>
        <a href="{{ route('manage.users.index') }}" class="app-button-secondary">Grįžti</a>
    </div>
</form>









