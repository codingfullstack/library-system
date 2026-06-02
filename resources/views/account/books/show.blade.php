<x-layouts::app :title="$book->title">
    @php
        $visibleCopies = $book->bookCopies;
        $availableCopies = $visibleCopies->where('status', 'laisva')->count();
        $loanedCopies = $visibleCopies->whereIn('status', ['išduota', 'vėluoja'])->count();
        $unavailableCopies = $visibleCopies->whereIn('status', ['prarasta', 'sugadinta', 'tvarkoma', 'nurašyta'])->count();
        $copyLibraries = $visibleCopies
            ->groupBy('library_id')
            ->map(function ($copies) {
                $library = $copies->first()?->library;
                $available = $copies->where('status', 'laisva')->count();
                $loaned = $copies->whereIn('status', ['išduota', 'vėluoja'])->count();

                return [
                    'name' => $library?->name ?? 'Biblioteka',
                    'address' => collect([$library?->address, $library?->city])->filter()->join(', '),
                    'status' => $available > 0
                        ? ['label' => 'Aktyvi', 'classes' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300']
                        : ($loaned > 0
                            ? ['label' => 'Išduota', 'classes' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300']
                            : ['label' => 'Neprieinama', 'classes' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300']),
                ];
            })
            ->values();

        $statusMeta = $availableCopies > 0
            ? ['label' => 'Aktyvi', 'description' => 'Knyga šiuo metu prieinama tavo bibliotekose.', 'classes' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300']
            : ($loanedCopies > 0
                ? ['label' => 'Išduota', 'description' => 'Šiuo metu knyga paimta skaitytojų.', 'classes' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300']
                : ($unavailableCopies === $visibleCopies->count() && $visibleCopies->isNotEmpty()
                    ? ['label' => 'Neprieinama', 'description' => 'Knyga šiuo metu neprieinama.', 'classes' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300']
                    : ['label' => 'Neprieinama', 'description' => $currentReservation ? 'Šiai knygai yra rezervacijų eilė.' : 'Knyga šiuo metu neprieinama.', 'classes' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300']));
    @endphp

    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1500px] space-y-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ $book->title }}</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $book->subtitle ?: 'Knygos informacija ir prieinamumas tavo bibliotekose.' }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('books.index') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.arrow-left class="size-4" />
                            Atgal į katalogą
                        </a>
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

                <section class="rounded-[24px] border border-zinc-200/80 bg-white px-6 py-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex flex-col gap-5 md:flex-row md:items-center">
                        <span class="inline-flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                            <flux:icon.book-open class="size-6" />
                        </span>

                        <div class="flex flex-wrap items-center gap-3 md:min-w-[190px]">
                            <span class="text-base font-semibold text-zinc-950 dark:text-white">Būsena</span>
                            <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $statusMeta['classes'] }}">
                                {{ $statusMeta['label'] }}
                            </span>
                        </div>

                        <div class="hidden h-12 w-px bg-zinc-200 dark:bg-zinc-800 md:block"></div>

                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $statusMeta['description'] }}</p>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-3">
                    <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 xl:col-span-2">
                        <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Bibliografinė informacija</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Knygos aprašymas, autoriai ir leidybiniai duomenys.</p>
                        </div>

                        <div class="grid gap-5 p-5 lg:grid-cols-[160px_1fr]">
                            <div class="flex h-56 w-40 items-center justify-center overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 text-lg font-bold uppercase text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                @if($book->cover_image_url)
                                    <img src="{{ $book->cover_image_url }}" alt="{{ $book->title }}" class="h-full w-full object-cover">
                                @else
                                    {{ str($book->title)->words(1, '')->substr(0, 2)->upper() }}
                                @endif
                            </div>

                            <div class="space-y-5">
                                <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    @foreach([
                                        'Autoriai' => $book->authors->pluck('name')->join(', ') ?: '-',
                                        'Kategorijos' => $book->categories->pluck('name')->join(', ') ?: '-',
                                        'Leidykla' => $book->publisher?->name ?: '-',
                                        'ISBN' => $book->isbn ?: '-',
                                        'Metai' => $book->publication_year ?: '-',
                                        'Kalba' => $book->language ?: '-',
                                        'Puslapiai' => $book->page_count ?: '-',
                                        'Leidimas' => $book->edition ?: '-',
                                    ] as $label => $value)
                                        <div class="rounded-lg bg-zinc-50 p-3 ring-1 ring-zinc-200 dark:bg-zinc-950/50 dark:ring-zinc-800">
                                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                                            <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $value }}</dd>
                                        </div>
                                    @endforeach
                                </dl>

                                <div class="rounded-lg bg-zinc-50 p-4 ring-1 ring-zinc-200 dark:bg-zinc-950/50 dark:ring-zinc-800">
                                    <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">Turinio aprašymas</h3>
                                    <p class="mt-2 text-sm leading-7 text-zinc-700 dark:text-zinc-300">{{ $book->description ?: 'Aprašymo nėra.' }}</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="space-y-6">
                        <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Bibliotekos</h2>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Kuriose tavo bibliotekose ši knyga yra.</p>
                            </div>
                            <div class="space-y-3 p-5">
                                @foreach($copyLibraries as $library)
                                    <div class="rounded-lg bg-zinc-50 p-3 ring-1 ring-zinc-200 dark:bg-zinc-950/50 dark:ring-zinc-800">
                                        <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $library['name'] }}</div>
                                        @if($library['address'])
                                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $library['address'] }}</div>
                                        @endif
                                        <div class="mt-2">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $library['status']['classes'] }}">
                                                {{ $library['status']['label'] }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>

                        @if($memberReservation)
                            <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Tavo rezervacija</h2>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Rezervacijos būsena ir galiojimas.</p>
                                </div>
                                <div class="space-y-3 p-5">
                                    <div class="rounded-lg bg-zinc-50 p-3 ring-1 ring-zinc-200 dark:bg-zinc-950/50 dark:ring-zinc-800">
                                        <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Būsena</div>
                                        <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">
                                            {{ $memberReservation->isCurrent() ? 'Paruošta atsiimti' : 'Laukianti eilėje' }}
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-zinc-50 p-3 ring-1 ring-zinc-200 dark:bg-zinc-950/50 dark:ring-zinc-800">
                                        <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Galioja iki</div>
                                        <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $memberReservation->expires_at?->format('Y-m-d H:i') ?: 'Terminas dar nepriskirtas' }}</div>
                                    </div>
                                    <livewire:reservations.cancel-reservation-form :reservation="$memberReservation" :key="'member-book-reservation-'.$memberReservation->id" />
                                </div>
                            </section>
                        @elseif($loanedCopies > 0)
                            <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Rezervuoti</h2>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Gali stoti į rezervacijos eilę.</p>
                                </div>
                                <div class="p-5">
                                    <livewire:reservations.create-reservation-form :book="$book" />
                                </div>
                            </section>
                        @elseif($availableCopies > 0)
                            <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <div class="p-5">
                                    <div class="flex items-start gap-3 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-900/40 dark:bg-sky-950/40 dark:text-sky-200">
                                        <flux:icon.information-circle class="mt-0.5 size-5 shrink-0" />
                                        <span>Ši knyga šiuo metu prieinama tavo bibliotekose, rezervacija nereikalinga.</span>
                                    </div>
                                </div>
                            </section>
                        @else
                            <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <div class="p-5">
                                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-200">
                                        Ši knyga šiuo metu neprieinama.
                                    </div>
                                </div>
                            </section>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>







