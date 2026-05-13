@php
    $copyDisplayCode = $copy->inventory_code ?: ('Kopija #'.$copy->id);
@endphp

<x-layouts::app :title="$copyDisplayCode">
    @php
        $statusLabels = \App\Models\BookCopy::statusLabels();
        $lifecycleLabels = \App\Models\BookCopy::lifecycleTargetLabels();
        $status = $copy->status ?: 'unknown';

        $copyMeta = [
            ['icon' => 'identification', 'label' => 'ID', 'value' => $copy->id],
            ['icon' => 'bookmark', 'label' => 'Būsena', 'badge' => true, 'value' => $statusLabels[$status] ?? $status],
            ['icon' => 'book-open', 'label' => 'Kopija', 'value' => $copy->inventory_code ?: '-'],
            ['icon' => 'tag', 'label' => 'Brūkšninis kodas', 'value' => $copy->barcode ?: '-'],
            ['icon' => 'shield-check', 'label' => 'Būklė', 'value' => ucfirst((string) $copy->condition_status) ?: '-'],
            ['icon' => 'building-library', 'label' => 'Filialas', 'value' => $copy->branch->name ?? '-'],
            ['icon' => 'map-pin', 'label' => 'Vieta', 'value' => $copy->location ? collect([$copy->location->name, $copy->location->room, $copy->location->shelf])->filter()->join(' / ') : '-'],
            ['icon' => 'calendar-days', 'label' => 'Įsigyta', 'value' => $copy->acquired_at?->format('Y-m-d') ?: '-'],
            ['icon' => 'squares-2x2', 'label' => 'QR reikšmė', 'value' => $copy->qr_code ?: '-'],
        ];
    @endphp

    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1560px] space-y-6">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <span class="text-emerald-700 dark:text-emerald-300">Valdymas</span>
                        <span>&gt;</span>
                        @if($copy->book)
                            <a href="{{ route('books.show', $copy->book) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ $copy->book->title }}</a>
                            <span>&gt;</span>
                        @endif
                        <span class="text-zinc-900 dark:text-white">{{ $copyDisplayCode }}</span>
                    </div>

                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ $copyDisplayCode }}</h1>
                                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                Peržiūrėkite šios knygos kopijos informaciją, būklę, vietą ir veiksmų istoriją.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            @can('update', $copy)
                                <a href="{{ route('manage.book-copies.edit', $copy) }}" title="Redaguoti egzempliorių" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                                    <flux:icon.pencil-square class="size-4" />
                                    Redaguoti
                                </a>
                            @endcan

                            @can('delete', $copy)
                                <form method="POST" action="{{ route('manage.book-copies.destroy', $copy) }}" onsubmit="return confirm('Ar tikrai nori ištrinti šį egzempliorių?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Ištrinti egzempliorių" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-red-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-red-500">
                                        <flux:icon.trash class="size-4" />
                                        Ištrinti
                                    </button>
                                </form>
                            @endcan

                            @if($copy->book)
                                <a href="{{ route('books.show', $copy->book) }}" title="Grįžti į knygos puslapi" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                    <flux:icon.arrow-left class="size-4" />
                                    Atgal į knygą
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                @if(session('success'))
                    <x-ui.alert>{{ session('success') }}</x-ui.alert>
                @endif

                @if(session('error'))
                    <x-ui.alert type="error">{{ session('error') }}</x-ui.alert>
                @endif

                @if($errors->any())
                    <x-ui.alert type="error">Nepavyko atnaujinti kopijos gyvenimo ciklo. Patikrink priežastį ir pasirinktą veiksmą.</x-ui.alert>
                @endif

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.8fr)_360px]">
                    <div class="space-y-6">
                        <section class="overflow-hidden rounded-[28px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Kopijos duomenys</h2>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Pagrindinė informacija apie šios knygos fizinę kopiją.</p>
                            </div>

                            <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach($copyMeta as $item)
                                    <div class="rounded-2xl border border-zinc-200/80 bg-zinc-50/70 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/40 {{ $item['label'] === 'Pastabos' ? 'sm:col-span-2 xl:col-span-3' : '' }}">
                                        <div class="flex gap-3">
                                            <div class="mt-0.5 flex size-8 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                                <flux:icon :name="$item['icon']" class="size-4" />
                                            </div>
                                            <div class="min-w-0">
                                                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $item['label'] }}</div>
                                                @if(! empty($item['badge']))
                                                    <div class="mt-2">
                                                        <x-ui.status-badge :status="$status" :label="$item['value']" />
                                                    </div>
                                                @else
                                                    <div class="mt-1 break-words text-sm font-medium text-zinc-950 dark:text-white">{{ $item['value'] }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                <div class="rounded-2xl border border-zinc-200/80 bg-zinc-50/70 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/40 sm:col-span-2 xl:col-span-3">
                                    <div class="flex gap-3">
                                        <div class="mt-0.5 flex size-8 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                            <flux:icon.document-text class="size-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Pastabos</div>
                                            <div class="mt-1 break-words text-sm font-medium text-zinc-950 dark:text-white">{{ $copy->notes ?: '-' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        @can('update', $copy)
                            <section class="overflow-hidden rounded-[28px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Gyvenimo ciklas</h2>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Valdyk paruošimą, sugadinimą, tvarkymą, atkūrimą ir nurašymą su priežastįmi.</p>
                                </div>

                                <div class="p-5">
                                    @if($copy->activeLoan)
                                        <div class="flex flex-col gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-950/30">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <div class="text-sm font-semibold text-amber-900 dark:text-amber-200">Kopija šiuo metu išduota.</div>
                                                    <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">Gyvenimo ciklo keitimas bus galimas po grąžinimo.</p>
                                                </div>
                                                <a href="{{ route('manage.book-copies.edit', $copy) }}" class="inline-flex h-10 items-center rounded-xl border border-amber-300 bg-white px-3 text-sm font-semibold text-amber-900 transition hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/20 dark:text-amber-200 dark:hover:bg-amber-900/30">
                                                    Peržiūrėti visa cikla
                                                </a>
                                            </div>
                                        </div>
                                    @elseif($copy->availableLifecycleTransitions() === [])
                                        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950/40 dark:text-zinc-300">
                                            Šiam statusui papildomu gyvenimo ciklo veiksmų nebera.
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('manage.book-copies.lifecycle.update', $copy) }}" class="space-y-4">
                                            @csrf
                                            @method('PATCH')

                                            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                                @foreach($copy->availableLifecycleTransitions() as $targetStatus)
                                                    <label class="cursor-pointer rounded-2xl border border-zinc-200/80 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                                                        <input type="radio" name="target_status" value="{{ $targetStatus }}" class="sr-only peer" @checked(old('target_status') === $targetStatus)>
                                                        <div class="rounded-xl border border-transparent peer-checked:border-teal-500 peer-checked:bg-teal-50 p-1 dark:peer-checked:border-teal-400 dark:peer-checked:bg-teal-950/30">
                                                            <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $lifecycleLabels[$targetStatus] ?? ($statusLabels[$targetStatus] ?? $targetStatus) }}</div>
                                                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Būsena taps: {{ $statusLabels[$targetStatus] ?? $targetStatus }}</div>
                                                        </div>
                                                    </label>
                                                @endforeach
                                            </div>
                                            @error('target_status') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                                            <div>
                                                <label for="reason_notes" class="app-label">Priežastis</label>
                                                <textarea id="reason_notes" name="reason_notes" rows="4" class="app-input mt-2" placeholder="Trumpai aprašyk, kas nutiko ir kodėl keičiama būsena." required>{{ old('reason_notes') }}</textarea>
                                                @error('reason_notes') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                            </div>

                                            <button type="submit" class="app-button-primary">Išsaugoti gyvenimo ciklą</button>
                                        </form>
                                    @endif
                                </div>
                            </section>
                        @endcan

                        <section class="overflow-hidden rounded-[28px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Būsenos istorija</h2>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Kas, kada ir del kokios priežastįes keitė kopijos būsena.</p>
                            </div>

                            <div class="p-5">
                                @if($copy->statusHistories->isEmpty())
                                    <x-ui.empty-state title="Istorijos dar nėra" description="Kai keisis būsena, čia matysi visą gyvavimo ciklą." />
                                @else
                                    <div class="space-y-3">
                                        @foreach($copy->statusHistories as $history)
                                            <div class="rounded-2xl border border-zinc-200/80 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                                <div class="grid gap-4 px-4 py-4 lg:grid-cols-[220px_220px_minmax(0,1fr)]">
                                                    <div>
                                                        <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $history->reasonLabel() }}</div>
                                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                            {{ $statusLabels[$history->from_status] ?? ($history->from_status ?: 'Pradinė būsena') }}
                                                            ->
                                                            {{ $statusLabels[$history->to_status] ?? $history->to_status }}
                                                        </div>
                                                    </div>
                                                    <div class="space-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                                                        <div>{{ $history->changed_at?->format('Y-m-d H:i') ?: '-' }}</div>
                                                        <div>{{ $history->user?->name ?: '-' }}</div>
                                                    </div>
                                                    <div class="text-sm text-zinc-700 dark:text-zinc-300">
                                                        {{ $history->reason_notes ?: '-' }}
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </section>

                        @if(auth()->user()?->isSuperAdmin())
                            <section class="overflow-hidden rounded-[28px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Veiksmų istorija</h2>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Paskutiniai audito įrašai, susiję su šiuo egzemplioriumi.</p>
                                </div>
                                <div class="p-5">
                                    @include('manage.audit-logs._list', [
                                        'auditLogs' => $auditLogs,
                                        'emptyTitle' => 'Veiksmų dar nėra',
                                        'emptyDescription' => 'Šiam egzemplioriui audito įrašų dar nesukaupta.',
                                    ])
                                </div>
                            </section>
                        @endif
                    </div>

                    <div class="space-y-6">
                        <section class="overflow-hidden rounded-[28px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">QR kodas</h2>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Nuskenuokite mobiliuoju įrenginiu.</p>
                            </div>

                            <div class="p-5">
                                <div class="flex flex-col items-center">
                                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800">
                                        <img src="{{ route('book-copies.qr', $copy->id) }}" alt="Knygos kopijos QR kodas" class="h-40 w-40 object-contain">
                                    </div>

                                    <a href="{{ route('book-copies.qr', $copy->id) }}" target="_blank" class="app-button-primary mt-5 w-full justify-center">
                                        Atsisiųsti QR
                                    </a>
                                </div>
                            </div>
                        </section>

                        @if($copy->activeLoan)
                            <section class="overflow-hidden rounded-[28px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Aktyvus išdavimas</h2>
                                </div>

                                <div class="space-y-4 p-5">
                                    <dl class="space-y-3 text-sm">
                                        <div class="flex items-start justify-between gap-3">
                                            <dt class="text-zinc-500 dark:text-zinc-400">Narys</dt>
                                            <dd class="text-right font-medium text-zinc-950 dark:text-white">{{ $copy->activeLoan->user?->name ?: '-' }}</dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-3">
                                            <dt class="text-zinc-500 dark:text-zinc-400">Išdavimo data</dt>
                                            <dd class="text-right font-medium text-zinc-950 dark:text-white">{{ $copy->activeLoan->borrowed_at?->format('Y-m-d H:i') ?: '-' }}</dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-3">
                                            <dt class="text-zinc-500 dark:text-zinc-400">Grąžinimo iki</dt>
                                            <dd class="text-right font-medium text-zinc-950 dark:text-white">{{ $copy->activeLoan->due_at?->format('Y-m-d') ?: 'Be termino' }}</dd>
                                        </div>
                                    </dl>

                                    <livewire:loans.return-book-copy-form :book-copy="$copy" :key="'return-book-copy-page-'.$copy->id" />
                                </div>
                            </section>
                        @endif

                        @if($copy->activeLoan)
                            <section class="overflow-hidden rounded-[28px] border border-amber-200 bg-amber-50 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/30">
                                <div class="px-5 py-4">
                                    <div class="text-sm font-semibold text-amber-900 dark:text-amber-200">Kopijos būsena</div>
                                    <p class="mt-2 text-sm text-amber-800 dark:text-amber-300">
                                        Kopija šiuo metu išduota. Gyvenimo ciklo keitimas bus galimas po grąžinimo.
                                    </p>
                                </div>
                            </section>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>







