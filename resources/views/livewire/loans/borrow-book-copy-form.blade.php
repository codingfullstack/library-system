<div class="pt-2">
    @if($canBorrow)
        <div class="space-y-3">
            @if($canIssuePreferred && $preferredReservation && ! $compactPreferredActions)
                <div class="app-priority-banner">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="app-priority-pill">Aktyvi rezervacija</span>
                                <span class="app-subtle-pill">Pirmumas šiam nariui</span>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-sky-800 dark:text-sky-200">Rezervuota šiam nariui</p>
                                <p class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">
                                    {{ $preferredReservation->user?->name }}
                                </p>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ $preferredReservation->user?->membership_number ?: $preferredReservation->user?->email }}
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-lg border border-white/70 bg-white/80 p-3 dark:border-zinc-800 dark:bg-zinc-950/40">
                                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Galioja iki</p>
                                    <p class="mt-1 text-sm font-semibold text-zinc-950 dark:text-white">
                                        {{ $preferredReservation->expires_at?->format('Y-m-d H:i') ?: '-' }}
                                    </p>
                                </div>

                                <div class="rounded-lg border border-white/70 bg-white/80 p-3 dark:border-zinc-800 dark:bg-zinc-950/40">
                                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Veiksmas</p>
                                    <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                        Greičiausias kelias išduoti kopiją pirmajam eilėje.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            wire:click="issuePreferred"
                            wire:loading.attr="disabled"
                            class="app-button-primary shrink-0 sm:min-w-36"
                        >
                            <span wire:loading.remove wire:target="issuePreferred">Išduoti jam</span>
                            <span wire:loading wire:target="issuePreferred">Išduodama...</span>
                        </button>
                    </div>
                </div>
            @endif

            @if(! $isOpen)
                <div class="flex flex-wrap items-center gap-2">
                    @if($canIssuePreferred && $preferredReservation && $compactPreferredActions)
                        <button
                            type="button"
                            wire:click="issuePreferred"
                            wire:loading.attr="disabled"
                            class="app-button-primary"
                        >
                            <span wire:loading.remove wire:target="issuePreferred">Išduoti jam</span>
                            <span wire:loading wire:target="issuePreferred">Išduodama...</span>
                        </button>
                    @endif

                    @unless($canIssuePreferred)
                        <button type="button" wire:click="open" class="app-button-secondary">
                            Išduoti
                        </button>
                    @endunless
                </div>
            @else
                <div class="fixed inset-0 z-50 bg-zinc-950/50" wire:key="borrow-modal-{{ $bookCopy->id }}">
                    <button type="button" wire:click="close" class="absolute inset-0 cursor-default" aria-label="Uždaryti"></button>

                    <form wire:submit="save" class="absolute inset-y-0 right-0 z-10 flex h-full w-96 max-w-[calc(100vw-1rem)] flex-col overflow-hidden border-l border-zinc-200 bg-white shadow-2xl sm:w-[32rem] dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-6 py-5 dark:border-zinc-800">
                            <div>
                                <h4 class="text-lg font-semibold text-zinc-950 dark:text-white">Išduoti kopiją</h4>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $bookCopy->inventory_code ?: ('Kopija #'.$bookCopy->id) }} bus priskirta pasirinktam nariui.
                                </p>
                            </div>

                            <button type="button" wire:click="close" title="Uždaryti išdavimo langą" aria-label="Uždaryti išdavimo langą" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-500 transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                <flux:icon.x-mark class="size-4" />
                            </button>
                        </div>

                        <div class="space-y-4 overflow-y-auto px-6 py-5">
                            @if(auth()->user()?->isSuperAdmin() && $issueLibraryName)
                                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                                    <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Išdavimas vyksta bibliotekoje</p>
                                    <p class="mt-2 text-sm font-semibold text-zinc-950 dark:text-white">{{ $issueLibraryName }}</p>
                                </div>
                            @endif

                            <div class="space-y-3">
                                <label for="borrow-member-search-{{ $bookCopy->id }}" class="app-label">Narys</label>

                                @if($selectedMember)
                                    <div class="rounded-2xl border border-teal-200 bg-teal-50 p-4 dark:border-teal-900/40 dark:bg-teal-500/10">
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
                                        id="borrow-member-search-{{ $bookCopy->id }}"
                                        type="search"
                                        wire:model.live.debounce.300ms="memberSearch"
                                        class="app-input"
                                        placeholder="Ieškoti pagal vardą, el. paštą, nario numeri arba telefona"
                                        autocomplete="off"
                                    >

                                    @if(trim($memberSearch) !== '')
                                        <div class="max-h-72 overflow-y-auto rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
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

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="borrow-due-at-{{ $bookCopy->id }}" class="app-label">Grąžinti iki</label>
                                    <input
                                        id="borrow-due-at-{{ $bookCopy->id }}"
                                        type="date"
                                        wire:model="dueAt"
                                        class="app-input mt-2"
                                        @disabled($noDueDate)
                                    >
                                    @error('dueAt')
                                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex items-end">
                                    <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                                        <input type="checkbox" wire:model.live="noDueDate" class="rounded border-zinc-300 text-teal-700 focus:ring-teal-600 dark:border-zinc-700 dark:bg-zinc-900">
                                        Be termino
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label for="borrow-notes-{{ $bookCopy->id }}" class="app-label">Pastabos</label>
                                <textarea
                                    id="borrow-notes-{{ $bookCopy->id }}"
                                    wire:model="notes"
                                    rows="3"
                                    class="app-input mt-2"
                                    placeholder="Papildoma informacija..."
                                ></textarea>
                                @error('notes')
                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            @error('bookCopy')
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-col gap-3 border-t border-zinc-200 px-6 py-4 sm:flex-row sm:justify-end dark:border-zinc-800">
                            <button type="button" wire:click="close" class="app-button-secondary">
                                Uždaryti
                            </button>

                            <button type="submit" class="app-button-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save">Išduoti kopiją</span>
                                <span wire:loading wire:target="save">Išduodama...</span>
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    @else
        <button
            type="button"
            class="app-button-secondary opacity-60"
            title="{{ $borrowUnavailableTitle ?? 'Negalima išduoti: kopija neatitinka išdavimo sąlygų.' }}"
            disabled
        >
            {{ $bookCopy->activeLoan ? 'Šiuo metu išduota' : 'Išduoti negalima' }}
        </button>
    @endif
</div>







