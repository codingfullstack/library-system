<x-layouts::app :title="$book->title">
    @php
        $authUser = auth()->user();
        $canEditBooks = $authUser?->isSuperAdmin();

        $preferredReservation = $book->reservations
            ->filter(fn ($reservation) => $reservation->isPending())
            ->sortBy('reserved_at')
            ->first();

        $visibleCopies = $book->bookCopies;
        $availableCopies = $visibleCopies->where('status', 'laisva')->count();
        $loanedCopies = $visibleCopies->where('status', 'išduota')->count();
        $unavailableCopies = $visibleCopies->whereIn('status', ['prarasta', 'sugadinta', 'tvarkoma', 'nurašyta'])->count();
        $activeReservationsCount = $book->reservations->filter(fn ($reservation) => $reservation->isPending())->count();
        $hasOnlyUnavailableCopies = $visibleCopies->isNotEmpty() && $unavailableCopies === $visibleCopies->count();

        [$availabilityLabel, $availabilityClasses] = match (true) {
            $availableCopies > 0 => ['Aktyvi', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'],
            $loanedCopies > 0 => ['Išduota', 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'],
            $hasOnlyUnavailableCopies => ['Neprieinama', 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'],
            $preferredReservation => ['Rezervuojama', 'bg-violet-100 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300'],
            default => ['Neprieinama', 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'],
        };

        $copyStatusLabels = \App\Models\BookCopy::statusLabels();
        $copyLifecycleLabels = [
            'aktyvi' => 'Aktyvus fondas',
            'issues' => 'Probleminiai egzemplioriai',
            'removed' => 'Nurašytas fondas',
        ];

        $bookMeta = [
            ['icon' => 'user', 'label' => 'Autorius', 'value' => $book->authors->pluck('name')->join(', ') ?: '-'],
            ['icon' => 'building-library', 'label' => 'Leidykla', 'value' => $book->publisher?->name ?: '-'],
            ['icon' => 'book-open-text', 'label' => 'ISBN', 'value' => $book->isbn ?: '-'],
            ['icon' => 'calendar-days', 'label' => 'Metai', 'value' => $book->publication_year ?: '-'],
            ['icon' => 'document-text', 'label' => 'Puslapiai', 'value' => $book->page_count ?: '-'],
            ['icon' => 'language', 'label' => 'Kalba', 'value' => $book->language ?: '-'],
            ['icon' => 'tag', 'label' => 'Kategorijos', 'value' => $book->categories->pluck('name')->join(', ') ?: '-'],
            ['icon' => 'numbered-list', 'label' => 'Leidimas', 'value' => $book->edition ?: '-'],
            ['icon' => 'clock', 'label' => 'Atnaujinta', 'value' => $book->updated_at?->format('Y-m-d H:i') ?: '-'],
        ];

        $allowedTabs = $authUser?->isSuperAdmin()
            ? ['copies', 'reservations', 'audit']
            : ['copies', 'reservations'];
        $initialTab = in_array((string) request('tab'), $allowedTabs, true) ? (string) request('tab') : 'copies';
    @endphp

    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1560px] space-y-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <span class="text-emerald-700 dark:text-emerald-300">Valdymas</span>
                            <span>&gt;</span>
                            <a href="{{ route('books.index') }}" class="hover:text-zinc-900 dark:hover:text-white">Knygos</a>
                            <span>&gt;</span>
                            <span class="text-zinc-900 dark:text-white">{{ $book->title }}</span>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ $book->title }}</h1>
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $availabilityClasses }}">
                                {{ $availabilityLabel }}
                            </span>
                        </div>

                        @if($book->categories->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach($book->categories as $category)
                                    <span class="inline-flex rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                        {{ $category->name }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('books.index') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.arrow-left class="size-4" />
                            Grįžti į knygų sąrašą
                        </a>

                        @if($canEditBooks)
                            <a href="{{ route('manage.books.edit', $book) }}" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                                <flux:icon.pencil-square class="size-4" />
                                Redaguoti knygą
                            </a>

                            <form method="POST" action="{{ route('manage.books.destroy', $book) }}" onsubmit="return confirm('Ar tikrai nori ištrinti šią knygą?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-red-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-red-500">
                                    <flux:icon.trash class="size-4" />
                                    Ištrinti knygą
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                @if (session('success'))
                    <x-ui.alert>{{ session('success') }}</x-ui.alert>
                @endif

                @if ($errors->any())
                    <x-ui.alert type="error">
                        <div class="font-semibold">Nepavyko išsaugoti:</div>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                @endif

                <div class="grid grid-cols-1 gap-6 2xl:grid-cols-[minmax(0,1.85fr)_420px]">
                    <section class="overflow-hidden rounded-[28px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="grid gap-6 p-6 xl:grid-cols-[140px_minmax(0,1fr)]">
                            <div class="overflow-hidden rounded-[22px] border border-zinc-200 bg-zinc-100 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                                @if($book->cover_image)
                                    <img src="{{ $book->cover_image }}" alt="{{ $book->title }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex aspect-[3/4] w-full items-center justify-center text-4xl font-bold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                                        {{ str($book->title)->substr(0, 2)->upper() }}
                                    </div>
                                @endif
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach($bookMeta as $item)
                                    <div class="flex gap-3 rounded-2xl border border-zinc-200/80 bg-zinc-50/80 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/40">
                                        <div class="mt-0.5 flex size-8 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                            <flux:icon :name="$item['icon']" class="size-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $item['label'] }}</div>
                                            <div class="mt-1 break-words text-sm font-medium text-zinc-950 dark:text-white">{{ $item['value'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    <div class="space-y-6">
                        <section class="overflow-hidden rounded-[28px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                <div>
                                    <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Kopijos bibliotekoje</div>
                                    <div class="mt-2 flex items-end gap-2">
                                        <span class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ $book->copies_count }}</span>
                                        <span class="mb-1 text-sm text-zinc-500 dark:text-zinc-400">vnt.</span>
                                    </div>
                                </div>

                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $availabilityClasses }}">
                                    {{ $availabilityLabel }}
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-3 p-5">
                                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 px-4 py-3 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                                    <div class="text-[11px] font-medium uppercase leading-tight tracking-wide text-emerald-700 dark:text-emerald-300">Laisvos</div>
                                    <div class="mt-2 text-2xl font-bold text-zinc-950 dark:text-white">{{ $availableCopies }}</div>
                                </div>
                                <div class="rounded-2xl border border-amber-200 bg-amber-50/70 px-4 py-3 dark:border-amber-900/50 dark:bg-amber-950/20">
                                    <div class="text-[11px] font-medium uppercase leading-tight tracking-wide text-amber-700 dark:text-amber-300">Išduotos</div>
                                    <div class="mt-2 text-2xl font-bold text-zinc-950 dark:text-white">{{ $loanedCopies }}</div>
                                </div>
                                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/40">
                                    <div class="text-[11px] font-medium uppercase leading-tight tracking-wide text-zinc-500 dark:text-zinc-400">Neprieinamos</div>
                                    <div class="mt-2 text-2xl font-bold text-zinc-950 dark:text-white">{{ $unavailableCopies }}</div>
                                </div>
                            </div>
                        </section>

                        <livewire:reservations.reservation-summary :book="$book" />

                        <section class="overflow-hidden rounded-[28px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                <h2 class="text-sm font-semibold text-zinc-950 dark:text-white">Knygos paštąbos</h2>
                                @if($canEditBooks)
                                    <a href="{{ route('manage.books.edit', $book) }}" title="Redaguoti knygos pastabas" aria-label="Redaguoti knygos pastabas" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-500 transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                        <flux:icon.pencil class="size-4" />
                                    </a>
                                @endif
                            </div>
                            <div class="px-5 py-4 text-sm leading-7 text-zinc-700 dark:text-zinc-300">
                                {{ $book->description ?: 'Pastabų nėra.' }}
                            </div>
                        </section>
                    </div>
                </div>

                <div
                    x-data="{
                        activeTab: '{{ $initialTab }}',
                        setTab(tab) {
                            this.activeTab = tab;
                            const url = new URL(window.location.href);
                            [
                                'audit-page',
                                'reservation-history-page',
                                'copy-page',
                                'page',
                            ].forEach((param) => url.searchParams.delete(param));
                            url.searchParams.set('tab', tab);
                            window.history.replaceState({}, '', url);
                        }
                    }"
                    x-init="activeTab = new URLSearchParams(window.location.search).get('tab') || '{{ $initialTab }}'"
                    class="space-y-6"
                >
                    <div class="flex flex-wrap items-center gap-6 border-b border-zinc-200 px-1 dark:border-zinc-800">
                        <button
                            type="button"
                            @click="setTab('copies')"
                            :class="activeTab === 'copies'
                                ? 'border-emerald-600 text-emerald-700 dark:text-emerald-300'
                                : 'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                            class="inline-flex h-12 items-center border-b-2 px-2 text-sm font-semibold transition"
                        >
                            Egzemplioriai
                        </button>
                        <button
                            type="button"
                            @click="setTab('reservations')"
                            :class="activeTab === 'reservations'
                                ? 'border-emerald-600 text-emerald-700 dark:text-emerald-300'
                                : 'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                            class="inline-flex h-12 items-center border-b-2 px-2 text-sm font-semibold transition"
                        >
                            Rezervacijos
                        </button>
                        @if($authUser?->isSuperAdmin())
                            <button
                                type="button"
                                @click="setTab('audit')"
                                :class="activeTab === 'audit'
                                    ? 'border-emerald-600 text-emerald-700 dark:text-emerald-300'
                                    : 'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                                class="inline-flex h-12 items-center border-b-2 px-2 text-sm font-semibold transition"
                            >
                                Veiksmų istorija
                            </button>
                        @endif
                    </div>

                    <section id="egzemplioriai" x-show="activeTab === 'copies'" x-cloak class="overflow-hidden rounded-[28px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Knygos kopijos</h2>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Filtruok šios knygos kopijas pagal gyvenimo cikla, statusa, filialą ir vieta.</p>
                            </div>

                            @if(in_array($authUser?->role, ['superadministratorius', 'administratorius', 'darbuotojas'], true))
                                <button type="button" x-on:click="Livewire.dispatch('open-book-copy-create-drawer')" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                                    Pridėti egzempliorių
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-4 px-5 py-4">
                        <form method="GET" action="{{ route('books.show', $book) }}" class="grid gap-3 xl:grid-cols-[220px_220px_220px_220px_auto_auto] xl:items-end">
                            <input type="hidden" name="tab" value="copies">
                            <div>
                                <label for="copy_lifecycle" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Gyvenimo ciklas</label>
                                <select id="copy_lifecycle" name="copy_lifecycle" class="app-input h-11 rounded-2xl border-zinc-200 bg-white shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Visi etapai</option>
                                    @foreach($copyLifecycleLabels as $lifecycleValue => $lifecycleLabel)
                                        <option value="{{ $lifecycleValue }}" @selected(request('copy_lifecycle') === $lifecycleValue)>{{ $lifecycleLabel }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="copy_status" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Statusas</label>
                                <select id="copy_status" name="copy_status" class="app-input h-11 rounded-2xl border-zinc-200 bg-white shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Visi statusai</option>
                                    @foreach($copyStatusLabels as $statusValue => $statusLabel)
                                        <option value="{{ $statusValue }}" @selected(request('copy_status') === $statusValue)>{{ $statusLabel }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="branch_id" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Filialas</label>
                                <select id="branch_id" name="branch_id" class="app-input h-11 rounded-2xl border-zinc-200 bg-white shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Visi filialai</option>
                                    @foreach($copyBranches as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="location_id" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Vieta</label>
                                <select id="location_id" name="location_id" class="app-input h-11 rounded-2xl border-zinc-200 bg-white shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Visos vietos</option>
                                    @foreach($copyLocations as $location)
                                        <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>
                                            {{ collect([$location->name, $location->room, $location->shelf])->filter()->join(' / ') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                <flux:icon.funnel class="mr-2 size-4" />
                                Filtruoti
                            </button>

                            <a href="{{ route('books.show', ['book' => $book, 'tab' => 'copies']) }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                Išvalyti
                            </a>
                        </form>

                        @if($bookCopies->total() === 0)
                            <x-ui.empty-state title="Kopijų nerasta" description="Šiai knygai dar nepridėta nei viena kopija." />
                        @else
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                Rodoma kopijų: {{ $bookCopies->firstItem() }}-{{ $bookCopies->lastItem() }} is {{ $bookCopies->total() }}
                            </div>

                            <div class="overflow-hidden rounded-[22px] border border-zinc-200/80 dark:border-zinc-800">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                        <thead class="bg-zinc-50/80 dark:bg-zinc-950/50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Inventorinis nr.</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Statusas</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Filialas</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Vieta</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Būklė</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Įsigyta</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Veiksmai</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                                            @foreach($bookCopies as $copy)
                                                @php
                                                    $status = $copy->status ?: 'unknown';
                                                    $preferredReservationForCopy = $book->reservations
                                                        ->filter(fn ($reservation) => $reservation->isPending())
                                                        ->where('library_id', $copy->library_id)
                                                        ->sortBy('reserved_at')
                                                        ->first();
                                                @endphp
                                                <tr class="align-middle transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                                    <td class="px-4 py-4 text-sm font-medium">
                                                        <a
                                                            href="{{ route('book-copies.show', $copy->id) }}"
                                                            title="Peržiūrėti kopija {{ $copy->inventory_code }}"
                                                            aria-label="Peržiūrėti kopija {{ $copy->inventory_code }}"
                                                            class="text-zinc-950 transition hover:text-emerald-700 dark:text-white dark:hover:text-emerald-300"
                                                        >
                                                            {{ $copy->inventory_code ?: '-' }}
                                                        </a>
                                                    </td>
                                                    <td class="px-4 py-4">
                                                        <div class="flex flex-col gap-2">
                                                            <x-ui.status-badge :status="$status" :label="$copyStatusLabels[$status] ?? $status" />
                                                            @if($preferredReservationForCopy)
                                                                <span class="inline-flex w-fit rounded-full bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">Yra rezervacija</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ $copy->branch?->name ?: '-' }}</td>
                                                    <td class="px-4 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                                        @if($copy->location)
                                                            {{ collect([$copy->location->name, $copy->location->room, $copy->location->shelf])->filter()->join(' / ') ?: '-' }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ ucfirst((string) $copy->condition_status) }}</td>
                                                    <td class="px-4 py-4 text-sm text-zinc-700 dark:text-zinc-300">{{ $copy->acquired_at?->format('Y-m-d') ?: '-' }}</td>
                                                    <td class="px-4 py-4">
                                                        <div class="flex items-center gap-2">
                                                            <a href="{{ route('book-copies.show', $copy->id) }}" title="Peržiūrėti kopija" aria-label="Peržiūrėti kopija" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-500 transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                                                <flux:icon.eye class="size-4" />
                                                            </a>

                                                            @if($copy->activeLoan)
                                                                <livewire:loans.return-book-copy-form :book-copy="$copy" :key="'return-copy-'.$copy->id" />
                                                            @endif

                                                            <livewire:loans.borrow-book-copy-form :book-copy="$copy" :preferred-reservation-id="$preferredReservationForCopy?->id" :key="'borrow-copy-'.$copy->id" />
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            @if($bookCopies->hasPages())
                                <div class="flex justify-end">
                                    {{ $bookCopies->appends([
                                        'tab' => 'copies',
                                        'copy_lifecycle' => request('copy_lifecycle'),
                                        'copy_status' => request('copy_status'),
                                        'branch_id' => request('branch_id'),
                                        'location_id' => request('location_id'),
                                    ])->links() }}
                                </div>
                            @endif
                        @endif
                    </div>
                </section>

                @if(in_array($authUser?->role, ['superadministratorius', 'administratorius', 'darbuotojas'], true))
                    <livewire:manage.book-copies.book-copy-create-drawer :book="$book" :key="'book-copy-create-drawer-'.$book->id" />
                @endif

                    <section id="rezervacijos" x-show="activeTab === 'reservations'" x-cloak class="grid grid-cols-1 gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
                        <x-ui.panel title="Nauja rezervacija" :description="$authUser?->role === 'narys' ? 'Rezervuok šią knygą sau.' : 'Sukurk rezervaciją pasirinktam nariui.'">
                            <livewire:reservations.create-reservation-form :book="$book" />
                        </x-ui.panel>

                        <div class="overflow-hidden rounded-[28px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Rezervacijų istorija</h2>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Visos šios knygos rezervacijos, naujausios viršuje.</p>
                            </div>

                            <div class="p-5">
                                <livewire:reservations.reservation-history :book="$book" />
                            </div>
                        </div>
                    </section>

                    @if($authUser?->isSuperAdmin())
                        <section id="veiksmų-istorija" x-show="activeTab === 'audit'" x-cloak class="overflow-hidden rounded-[28px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Veiksmų istorija</h2>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Vienoje vietoje matysi knygos, jos egzempliorių, rezervacijų ir išdavimų veiksmų istorija.</p>
                            </div>

                            <div class="p-5">
                                @include('manage.audit-logs._list', [
                                    'auditLogs' => $auditLogs,
                                    'tab' => 'audit',
                                    'emptyTitle' => 'Veiksmų dar nėra',
                                    'emptyDescription' => 'Šiai knygai audito įrašų dar nesukaupta.',
                                ])
                            </div>
                        </section>
                    @endif
                </div>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>







