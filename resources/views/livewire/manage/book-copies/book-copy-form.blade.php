<form wire:submit="save" class="space-y-6">
    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $selectedBook?->title ?: 'Knyga' }}</p>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
            {{ $selectedBook?->authors?->pluck('name')->join(', ') ?: 'Autorius nenurodytas' }}
        </p>
        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
            ISBN: {{ $selectedBook?->isbn ?: '-' }}
            @if($selectedBook?->publisher)
                - Leidykla: {{ $selectedBook->publisher->name }}
            @endif
            @if($selectedBook?->categories?->isNotEmpty())
                - Kategorijos: {{ $selectedBook->categories->pluck('name')->join(', ') }}
            @endif
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @if(auth()->user()?->isSuperAdmin() && ! $isEditing)
            <div class="lg:col-span-2">
                <label for="copy-library-id" class="app-label">Biblioteka</label>
                <select id="copy-library-id" wire:model.live="selectedLibraryId" class="app-input" required>
                    <option value="">Pasirinkti biblioteką</option>
                    @foreach($libraries as $library)
                        <option value="{{ $library->id }}">{{ $library->name }}</option>
                    @endforeach
                </select>
                @error('selectedLibraryId') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        @endif

        <div>
            <label for="branch_id" class="app-label">Filialas</label>
            @if(auth()->user()?->role === \App\Models\User::ROLE_STAFF)
                <div id="branch_id" class="app-input flex items-center bg-zinc-50 text-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-300">
                    @if($staffBranch)
                        {{ $staffBranch->name }}{{ $staffBranch->code ? ' ('.$staffBranch->code.')' : '' }}
                    @else
                        Filialas nepriskirtas
                    @endif
                </div>
            @else
                <select id="branch_id" wire:model.live="branchId" class="app-input" required>
                    <option value="">Pasirinkti filialą</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">
                            {{ $branch->name }}{{ $branch->code ? ' ('.$branch->code.')' : '' }}
                        </option>
                    @endforeach
                </select>
            @endif
            @error('branchId') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="location_id" class="app-label">Vieta</label>
            <select id="location_id" wire:model.live="locationId" class="app-input">
                <option value="">Pasirinkti vietą</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}">
                        {{ $location->branch?->name ? $location->branch->name.' / ' : '' }}{{ $location->name }}{{ $location->room ? ' / '.$location->room : '' }}{{ $location->shelf ? ' / '.$location->shelf : '' }}
                    </option>
                @endforeach
            </select>
            @error('locationId') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="inventory_code" class="app-label">Kopija</label>
            <input id="inventory_code" type="text" wire:model="inventoryCode" class="app-input" required>
            @error('inventoryCode') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
            <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">QR kodas</p>
            <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                {{ $bookCopy?->qr_code ?: 'Bus sugeneruotas automatiškai.' }}
            </p>
        </div>

        <div>
            <label for="barcode" class="app-label">Brūkšninis kodas</label>
            <input id="barcode" type="text" wire:model="barcode" class="app-input">
            @error('barcode') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="acquired_at" class="app-label">Įsigijimo data</label>
            <input id="acquired_at" type="date" wire:model="acquiredAt" class="app-input">
            @error('acquiredAt') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        @if(! $isEditing)
            <div>
                <label for="status" class="app-label">Pradinė būsena</label>
                <select id="status" wire:model="status" class="app-input" required>
                    @foreach($creatableStatusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                    Vėlesni būsenos pakeitimai valdomi per kopijos gyvenimo ciklą.
                </p>
                @error('status') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        @else
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Dabartinė būsena</p>
                <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                    {{ $statusOptions[$status] ?? $status }}
                </p>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                    Būsenos keitimus daryk per gyvenimo ciklo valdymo bloką kopijos peržiūroje.
                </p>
            </div>
        @endif

        <div>
            <label for="condition_status" class="app-label">Fizinė būklė</label>
            <select id="condition_status" wire:model="conditionStatus" class="app-input" required>
                @foreach($conditionOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('conditionStatus') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="lg:col-span-2">
            <label for="notes" class="app-label">Pastabos</label>
            <textarea id="notes" wire:model="notes" rows="4" class="app-input"></textarea>
            @error('notes') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
    </div>

    @error('selectedBook')
        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    <div class="{{ $isEditing ? 'flex flex-col gap-3 sm:flex-row' : 'sticky bottom-0 -mx-6 mt-6 flex flex-col gap-3 border-t border-zinc-200 bg-white px-6 py-4 sm:flex-row sm:justify-end dark:border-zinc-800 dark:bg-zinc-950' }}">
        <button type="submit" class="app-button-primary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">{{ $isEditing ? 'Išsaugoti pakeitimus' : 'Pridėti kopiją' }}</span>
            <span wire:loading wire:target="save">{{ $isEditing ? 'Saugoma...' : 'Pridedama...' }}</span>
        </button>

        @if($drawerMode)
            <button type="button" wire:click="$dispatch('book-copy-drawer-close')" class="app-button-secondary">
                Uždaryti
            </button>
        @else
            <a href="{{ $bookCopy ? route('book-copies.show', $bookCopy) : route('manage.book-copies.create', array_filter(['search' => request('search'), 'library_id' => $selectedLibraryId])) }}" class="app-button-secondary">
                {{ $bookCopy ? 'Grįžti' : 'Uždaryti' }}
            </a>
        @endif
    </div>
</form>







