<form wire:submit="save" class="space-y-5">
    @if($successMessage)
        <x-ui.alert class="mb-0">
            {{ $successMessage }}
        </x-ui.alert>
    @endif

    @if($actor && $reservationBlockedMessage)
        <x-ui.alert type="info" class="mb-0">
            {{ $reservationBlockedMessage }}
        </x-ui.alert>
    @endif

    @if($actor && $actor->role === 'narys' && $isReservable)
        <div class="app-muted-card">
            <p class="app-label">Rezervuos</p>
            <p class="mt-2 text-sm font-semibold text-zinc-950 dark:text-white">{{ $actor->name }}</p>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                {{ $actor->membership_number ?: $actor->email }}
            </p>
        </div>
    @elseif($actor && $usesMemberSearch)
        <div class="space-y-3">
            <label for="reservation-member-search" class="app-label">Narys</label>

            @if($selectedMember)
                <div class="rounded-lg border border-teal-200 bg-teal-50 p-4 dark:border-teal-900/40 dark:bg-teal-500/10">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-zinc-950 dark:text-white">
                                {{ $selectedMember['name'] }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">
                                {{ $selectedMember['membership_number'] ?: $selectedMember['email'] }}
                            </p>
                            @if(! empty($selectedMember['library_name']))
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    Biblioteka: {{ $selectedMember['library_name'] }}
                                </p>
                            @endif
                            @if($selectedMember['phone'])
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $selectedMember['phone'] }}</p>
                            @endif
                        </div>

                        <button type="button" wire:click="clearMember" class="text-sm font-medium text-teal-800 hover:text-teal-950 dark:text-teal-300 dark:hover:text-teal-100">
                            Keisti
                        </button>
                    </div>
                </div>
            @else
                <input
                    id="reservation-member-search"
                    type="search"
                    wire:model.live.debounce.300ms="memberSearch"
                    class="app-input"
                    placeholder="Ieškoti pagal vardą, el. paštą, nario numerį arba telefoną"
                    autocomplete="off"
                >

                @if(trim($memberSearch) !== '')
                    <div class="max-h-72 overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        @forelse($members as $member)
                            <button
                                type="button"
                                wire:click="selectMember({{ $member->id }})"
                                class="block w-full border-b border-zinc-100 px-4 py-3 text-left last:border-b-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60"
                            >
                                <span class="block text-sm font-semibold text-zinc-950 dark:text-white">{{ $member->name }}</span>
                                <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $member->membership_number ?: $member->email }}
                                    @if($member->phone)
                                        · {{ $member->phone }}
                                    @endif
                                </span>
                                @if($member->library?->name)
                                    <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                                        Biblioteka: {{ $member->library->name }}
                                    </span>
                                @endif
                            </button>
                        @empty
                            <div class="px-4 py-5 text-sm text-zinc-500 dark:text-zinc-400">
                                Narių pagal šią paiešką nerasta.
                            </div>
                        @endforelse
                    </div>
                @endif
            @endif

            @error('selectedMemberId')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        @if($isReservable && $hasQueueAhead)
            <x-ui.alert type="info" class="mb-0">
                Šiai knygai jau yra aktyvi rezervacija. Nauja rezervacija bus įtraukta į eilę, o galiojimo terminas bus priskirtas, kai ateis jos eilė.
            </x-ui.alert>
        @elseif($isReservable)
            <div>
                <label for="reservation-expires-at" class="app-label">Galioja iki</label>
                <input
                    id="reservation-expires-at"
                    type="datetime-local"
                    wire:model="expiresAt"
                    class="app-input mt-2"
                >
                @error('expiresAt')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        @endif

        @if($actor?->isSuperAdmin() && $selectedLibraryName)
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Rezervacija bus kuriama bibliotekoje</p>
                <p class="mt-2 text-sm font-semibold text-zinc-950 dark:text-white">{{ $selectedLibraryName }}</p>
            </div>
        @endif
    @elseif(! $actor)
        <x-ui.empty-state
            title="Prisijunkite"
            description="Norint rezervuoti knygą, reikia prisijungti."
        />
    @endif

    @if($actor && ($isReservable || $canChooseScope))
        <div class="space-y-3">
            <p class="app-label">Rezervacijos apimtis</p>

            <div class="grid gap-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-800 dark:bg-zinc-950/40">
                    <input
                        type="radio"
                        name="reservation_scope"
                        wire:model.live="scope"
                        value="{{ \App\Models\Reservation::SCOPE_BRANCH }}"
                        class="mt-1"
                    >
                    <span>
                        <span class="block font-semibold text-zinc-950 dark:text-white">
                            {{ $actor?->role === \App\Models\User::ROLE_STAFF ? 'Tik mano filiale' : 'Konkretus filialas' }}
                        </span>
                        @if($actor?->role === \App\Models\User::ROLE_STAFF && $staffBranchName)
                            <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                                Filialas: {{ $staffBranchName }}
                            </span>
                        @endif
                    </span>
                </label>

                @if($scope === \App\Models\Reservation::SCOPE_BRANCH && $actor?->role !== \App\Models\User::ROLE_STAFF)
                    <div>
                        <label for="reservation-branch-id" class="sr-only">Filialas</label>
                        <select id="reservation-branch-id" wire:model.live="branchId" class="app-input">
                            <option value="">Pasirinkite filialą</option>
                            @foreach($branchOptions as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branchId')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-800 dark:bg-zinc-950/40">
                    <input
                        type="radio"
                        name="reservation_scope"
                        wire:model.live="scope"
                        value="{{ \App\Models\Reservation::SCOPE_LIBRARY }}"
                        class="mt-1"
                    >
                    <span>
                        <span class="block font-semibold text-zinc-950 dark:text-white">Visoje bibliotekoje</span>
                        <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                            Skaitytojas gali būti pakviestas pasiimti knygą iš bet kurio šios bibliotekos filialo.
                        </span>
                    </span>
                </label>
            </div>

            @error('scope')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    @endif

    @if($actor && $isReservable)
        <div>
            <label for="reservation-notes" class="app-label">Pastabos</label>
            <textarea
                id="reservation-notes"
                wire:model="notes"
                rows="4"
                class="app-input mt-2"
                placeholder="Papildoma informacija..."
            ></textarea>
            @error('notes')
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        @error('book_id')
            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror

        <button type="submit" class="app-button-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove>
                {{ $usesMemberSearch ? 'Sukurti rezervaciją' : 'Rezervuoti sau' }}
            </span>
            <span wire:loading>
                Kuriama...
            </span>
        </button>
    @endif
</form>







