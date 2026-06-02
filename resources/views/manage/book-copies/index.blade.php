<x-layouts::app :title="'Egzemplioriai'">
    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1500px] space-y-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">Egzemplioriai</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Peržiūrėkite ir tvarkykite bibliotekos knygų egzempliorius</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('exports.list', array_merge(request()->query(), ['resource' => 'book-copies'])) }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.arrow-down-tray class="size-4" />
                            Eksportuoti
                        </a>

                        <a href="{{ route('manage.book-copies.create') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                            <flux:icon.plus class="size-4" />
                            Pridėti egzempliorių
                            <flux:icon.chevron-down class="size-4" />
                        </a>
                    </div>
                </div>

                @if(session('success'))
                    <x-ui.alert>{{ session('success') }}</x-ui.alert>
                @endif

                @if(session('error'))
                    <x-ui.alert type="error">{{ session('error') }}</x-ui.alert>
                @endif

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <flux:icon.squares-plus class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Visi egzemplioriai</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['total'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Iš viso</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">
                                <flux:icon.check-circle class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Laisvi</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['laisva'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Galimi išdavimai</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                <flux:icon.book-open-text class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Išduoti</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['išduota'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Šiuo metu</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                                <flux:icon.exclamation-circle class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Neprieinami</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['unavailable'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Sugadinti, prarasti, tvarkomi</div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="px-5 py-4">
                        <form method="GET" action="{{ route('manage.book-copies.index') }}" class="grid gap-3 xl:grid-cols-[minmax(320px,1.4fr)_220px_220px_auto_auto] xl:items-center">
                            <div class="relative xl:min-w-0">
                                <input
                                    type="text"
                                    name="search"
                                    value="{{ request('search') }}"
                                    placeholder="Ieškoti pagal knygą, inventoriaus kodą, filialą ar vietą..."
                                    class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 pl-11 shadow-none dark:border-zinc-700 dark:bg-zinc-950"
                                >
                                <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400">
                                    <flux:icon.magnifying-glass class="size-4" />
                                </div>
                            </div>

                            <div class="xl:min-w-0">
                                <select name="status" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Būsena</option>
                                    @foreach($statusLabels as $statusValue => $statusLabel)
                                        <option value="{{ $statusValue }}" @selected(request('status') === $statusValue)>{{ $statusLabel }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="xl:min-w-0">
                                <select name="branch_id" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Filialas</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit" class="app-button-secondary h-11 rounded-2xl px-4">
                                <flux:icon.funnel class="mr-2 size-4" />
                                Filtruoti
                            </button>

                            <a href="{{ route('manage.book-copies.index') }}" class="app-button-secondary h-11 rounded-2xl px-4">
                                Išvalyti
                            </a>
                        </form>
                    </div>
                </section>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    @if($bookCopies->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                <thead class="bg-zinc-50/80 dark:bg-zinc-950/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left"><input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500"></th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Knyga</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Kopija</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Filialas</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Vieta</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Būsena</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Būklė</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Atnaujinta</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Veiksmai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @foreach($bookCopies as $copy)
                                        @php
                                            $statusClasses = match ($copy->status) {
                                                \App\Models\BookCopy::STATUS_AVAILABLE => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                                                \App\Models\BookCopy::STATUS_LOANED => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                                                \App\Models\BookCopy::STATUS_LOST => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
                                                \App\Models\BookCopy::STATUS_DAMAGED => 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-300',
                                                \App\Models\BookCopy::STATUS_MAINTENANCE => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
                                                default => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300',
                                            };
                                            $locationLabel = $copy->location
                                                ? collect([$copy->location->name, $copy->location->room, $copy->location->shelf])->filter()->join(' / ')
                                                : '-';
                                        @endphp
                                        <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                            <td class="px-4 py-4 align-middle"><input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500"></td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex items-start gap-3">
                                                    <div class="flex h-14 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md border border-zinc-200 bg-zinc-100 text-[10px] font-semibold uppercase text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                                        @if($copy->book?->cover_image_url)
                                                            <img src="{{ $copy->book->cover_image_url }}" alt="{{ $copy->book->title }}" class="h-full w-full object-cover">
                                                        @else
                                                            {{ str($copy->book?->title ?? 'BK')->words(1, '')->substr(0, 2)->upper() }}
                                                        @endif
                                                    </div>
                                                    <div class="min-w-0">
                                                        <a href="{{ route('book-copies.show', $copy) }}" class="font-semibold text-zinc-950 transition hover:text-emerald-700 dark:text-white dark:hover:text-emerald-300">
                                                            {{ $copy->book?->title ?? '-' }}
                                                        </a>
                                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $copy->book?->isbn ?? '-' }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $copy->inventory_code }}</div>
                                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $copy->barcode ?: 'Be barkodo' }}</div>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $copy->branch?->name ?: '-' }}</td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $locationLabel }}</td>
                                            <td class="px-4 py-4 align-middle">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">
                                                    {{ $copy->statusLabel() }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ ucfirst((string) $copy->condition_status) }}</td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $copy->updated_at?->format('Y-m-d') ?? '-' }}</td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex items-center gap-3 text-zinc-500 dark:text-zinc-400">
                                                    <a href="{{ route('book-copies.show', $copy) }}" title="Peržiūrėti egzempliorių" aria-label="Peržiūrėti egzempliorių" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:text-white">
                                                        <flux:icon.eye class="size-4" />
                                                    </a>
                                                    @can('update', $copy)
                                                        <a href="{{ route('manage.book-copies.edit', $copy) }}" title="Redaguoti egzempliorių" aria-label="Redaguoti egzempliorių" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:text-white">
                                                            <flux:icon.pencil-square class="size-4" />
                                                        </a>
                                                    @endcan
                                                    @can('delete', $copy)
                                                        <form method="POST" action="{{ route('manage.book-copies.destroy', $copy) }}" onsubmit="return confirm('Ar tikrai nori ištrinti šį egzempliorių?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" title="Ištrinti egzempliorių" aria-label="Ištrinti egzempliorių" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white transition hover:text-red-600 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:text-red-300">
                                                                <flux:icon.trash class="size-4" />
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-zinc-200 px-5 py-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
                            <div>Rodoma {{ $bookCopies->firstItem() }}-{{ $bookCopies->lastItem() }} is {{ $bookCopies->total() }}</div>
                            <div>{{ $bookCopies->links() }}</div>
                        </div>
                    @else
                        <div class="p-6">
                            <x-ui.empty-state title="Egzempliorių nerasta" description="Pabandykite pakeisti paiešką arba pridėti naują egzempliorių." />
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>







