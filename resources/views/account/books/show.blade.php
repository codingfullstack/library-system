<x-layouts::app :title="$book->title">
    @php
        $visibleCopies = $book->bookCopies;
        $availableCopies = $visibleCopies->where('status', 'available')->count();
        $loanedCopies = $visibleCopies->whereIn('status', ['loaned', 'overdue'])->count();
        $unavailableCopies = $visibleCopies->whereIn('status', ['lost', 'damaged', 'maintenance', 'withdrawn'])->count();

        $availabilityLabel = $availableCopies > 0
            ? 'Yra laisvu kopiju'
            : ($loanedCopies > 0
                ? 'Siuo metu laisvu kopiju nera'
                : ($unavailableCopies === $visibleCopies->count() && $visibleCopies->isNotEmpty()
                    ? 'Knyga siuo metu neprieinama'
                    : ($currentReservation ? 'Yra rezervaciju eile' : 'Siuo metu laisvu kopiju nera')));
    @endphp

    <x-ui.page>
        <x-ui.page-header
            eyebrow="Knyga"
            :title="$book->title"
            :description="$book->subtitle"
        >
            <x-slot:actions>
                <a href="{{ route('books.index') }}" class="app-button-secondary">Atgal i kataloga</a>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))
            <x-ui.alert>{{ session('success') }}</x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.alert type="error">
                <div class="font-semibold">Nepavyko issaugoti:</div>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <x-ui.panel title="Aprasymas" description="Bibliografine ir turinio informacija apie knyga.">
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="app-muted-card">
                            <dt class="app-label">Autoriai</dt>
                            <dd class="mt-2 text-sm font-medium text-zinc-950 dark:text-white">{{ $book->authors->pluck('name')->join(', ') ?: '-' }}</dd>
                        </div>
                        <div class="app-muted-card">
                            <dt class="app-label">Kategorijos</dt>
                            <dd class="mt-2 text-sm font-medium text-zinc-950 dark:text-white">{{ $book->categories->pluck('name')->join(', ') ?: '-' }}</dd>
                        </div>
                        <div class="app-muted-card">
                            <dt class="app-label">Leidykla</dt>
                            <dd class="mt-2 text-sm font-medium text-zinc-950 dark:text-white">{{ $book->publisher?->name ?: '-' }}</dd>
                        </div>
                        <div class="app-muted-card">
                            <dt class="app-label">ISBN</dt>
                            <dd class="mt-2 text-sm font-medium text-zinc-950 dark:text-white">{{ $book->isbn ?: '-' }}</dd>
                        </div>
                        <div class="app-muted-card">
                            <dt class="app-label">Metai</dt>
                            <dd class="mt-2 text-sm font-medium text-zinc-950 dark:text-white">{{ $book->publication_year ?: '-' }}</dd>
                        </div>
                        <div class="app-muted-card">
                            <dt class="app-label">Kalba</dt>
                            <dd class="mt-2 text-sm font-medium text-zinc-950 dark:text-white">{{ $book->language ?: '-' }}</dd>
                        </div>
                        <div class="app-muted-card">
                            <dt class="app-label">Puslapiai</dt>
                            <dd class="mt-2 text-sm font-medium text-zinc-950 dark:text-white">{{ $book->page_count ?: '-' }}</dd>
                        </div>
                        <div class="app-muted-card">
                            <dt class="app-label">Leidimas</dt>
                            <dd class="mt-2 text-sm font-medium text-zinc-950 dark:text-white">{{ $book->edition ?: '-' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950/40">
                        <h3 class="text-base font-semibold text-zinc-950 dark:text-white">Turinio aprasymas</h3>
                        <p class="mt-3 text-sm leading-7 text-zinc-700 dark:text-zinc-300">{{ $book->description ?: 'Aprasymo nera.' }}</p>
                    </div>
                </x-ui.panel>
            </div>

            <div class="space-y-6">
                <div class="app-card">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Prieinamumas tavo bibliotekoje</p>
                    <div class="mt-3 text-xl font-semibold text-zinc-950 dark:text-white">{{ $availabilityLabel }}</div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="app-muted-card">
                            <div class="app-label">Visos kopijos</div>
                            <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $book->copies_count }}</div>
                        </div>
                        <div class="app-muted-card">
                            <div class="app-label">Laisvos kopijos</div>
                            <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $book->available_copies_count }}</div>
                        </div>
                    </div>
                </div>

                @if($memberReservation)
                    <x-ui.panel title="Tavo rezervacija" description="Tavo vieta eileje ir rezervacijos busena.">
                        <div class="space-y-3">
                            <div class="app-muted-card">
                                <div class="app-label">Busena</div>
                                <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">
                                    {{ $memberReservation->isCurrent() ? 'Paruosta atsiimti' : 'Laukianti eileje' }}
                                </div>
                            </div>
                            <div class="app-muted-card">
                                <div class="app-label">Galioja iki</div>
                                <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $memberReservation->expires_at?->format('Y-m-d H:i') ?: 'Terminas dar nepriskirtas' }}</div>
                            </div>
                            <livewire:reservations.cancel-reservation-form :reservation="$memberReservation" :key="'member-book-reservation-'.$memberReservation->id" />
                        </div>
                    </x-ui.panel>
                @elseif($loanedCopies > 0)
                    <x-ui.panel title="Rezervuoti" description="Jei siuo metu nera laisvu kopiju, gali stoti i eile.">
                        <livewire:reservations.create-reservation-form :book="$book" />
                    </x-ui.panel>
                @elseif($availableCopies > 0)
                    <x-ui.panel title="Knyga prieinama" description="Siuo metu tavo bibliotekoje yra laisvu sios knygos kopiju.">
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-200">
                            Rezervacija siuo metu nereikalinga, nes bibliotekoje yra laisvu sios knygos kopiju.
                        </div>
                    </x-ui.panel>
                @else
                    <x-ui.panel title="Knyga neprieinama" description="Siuo metu tavo bibliotekoje nera kopiju, kurias butu galima isduoti ar rezervuoti.">
                        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-200">
                            Visos sios knygos kopijos siuo metu pazymetos kaip neprieinamos.
                        </div>
                    </x-ui.panel>
                @endif
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>
