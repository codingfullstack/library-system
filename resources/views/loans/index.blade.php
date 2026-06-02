<x-layouts::app :title="__('Išduotos knygos')">
    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1500px] space-y-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">Išduotos knygos</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            Peržiūrėkite visas šiuo metu išduotas knygas
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('exports.list', array_merge(request()->query(), ['resource' => 'loans'])) }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.arrow-down-tray class="size-4" />
                            Eksportuoti
                        </a>

                        <button type="button" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.funnel class="size-4" />
                            Filtruoti
                        </button>
                    </div>
                </div>

                @if(session('success'))
                    <x-ui.alert type="success">
                        {{ session('success') }}
                    </x-ui.alert>
                @endif

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <flux:icon.book-open-text class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Išduota knygų</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['active_loans_count'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Šiuo metu</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">
                                <flux:icon.users class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Vartotojų</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['unique_members_count'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Unikaliu vartotojų</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                <flux:icon.calendar-days class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Grąžinimo šiandien</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['due_today_count'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Knygos</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300">
                                <flux:icon.clock class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Vėluojančios knygos</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['overdue_loans_count'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Iš viso</div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="px-5 py-4">
                        <form method="GET" action="{{ route('loans.index') }}" class="grid gap-3 xl:grid-cols-[minmax(320px,1.5fr)_180px_180px_210px_auto_auto] xl:items-center">
                            <div class="relative xl:min-w-0">
                                <input
                                    id="search"
                                    type="text"
                                    name="search"
                                    value="{{ request('search') }}"
                                    placeholder="Ieškoti pagal knygos pavadinimą, autorių ar ISBN..."
                                    class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 pl-11 shadow-none dark:border-zinc-700 dark:bg-zinc-950"
                                >
                                <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400">
                                    <flux:icon.magnifying-glass class="size-4" />
                                </div>
                            </div>

                            <div class="xl:min-w-0">
                                <select id="member_id" name="member_id" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Vartotojas</option>
                                    @foreach($members as $member)
                                        <option value="{{ $member->id }}" {{ (string) request('member_id') === (string) $member->id ? 'selected' : '' }}>
                                            {{ $member->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="xl:min-w-0">
                                <select id="status" name="status" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Būsena</option>
                                    <option value="aktyvi" {{ request('status') === 'aktyvi' ? 'selected' : '' }}>Aktyvios</option>
                                    <option value="vėluoja" {{ request('status') === 'vėluoja' ? 'selected' : '' }}>Vėluojančios</option>
                                    <option value="grąžinta" {{ request('status') === 'grąžinta' ? 'selected' : '' }}>Grąžintos</option>
                                </select>
                            </div>

                            <div class="xl:min-w-0">
                                <input type="date" id="due_date" name="due_date" value="{{ request('due_date') }}" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                            </div>

                            <div class="flex items-center gap-3 xl:justify-start">
                                <button type="submit" class="app-button-secondary h-11 rounded-2xl px-4">
                                    <flux:icon.funnel class="mr-2 size-4" />
                                    Filtruoti
                                </button>

                                <a href="{{ route('loans.index') }}" class="app-button-secondary h-11 rounded-2xl px-4">
                                    Išvalyti
                                </a>
                            </div>

                            @if(auth()->user()?->isSuperAdmin())
                                <input type="hidden" name="library_id" value="{{ request('library_id') }}">
                            @endif
                            <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                        </form>
                    </div>
                </section>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    @if($loans->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                <thead class="bg-zinc-50/80 dark:bg-zinc-950/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left">
                                            <input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500">
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Knyga</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Vartotojas</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Išdavimo data</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Grąžinimo data</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Būsena</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Kopija</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Veiksmai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @foreach($loans as $loan)
                                        @php
                                            $dueDate = $loan->due_at;
                                            $daysUntilDue = $dueDate ? now()->startOfDay()->diffInDays($dueDate->copy()->startOfDay(), false) : null;
                                            $statusMeta = $loan->is_overdue
                                                ? ['label' => 'Vėluoja', 'classes' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300']
                                                : (($daysUntilDue !== null && $daysUntilDue <= 2)
                                                    ? ['label' => 'Grąžinti netrukus', 'classes' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300']
                                                    : ['label' => 'Aktyvi', 'classes' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300']);
                                        @endphp
                                        <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                            <td class="px-4 py-4 align-middle">
                                                <input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500">
                                            </td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex items-start gap-3">
                                                    <div class="flex h-14 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md border border-zinc-200 bg-zinc-100 text-[10px] font-semibold uppercase text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                                        @if($loan->bookCopy?->book?->cover_image_url)
                                                            <img src="{{ $loan->bookCopy->book->cover_image_url }}" alt="{{ $loan->bookCopy->book->title }}" class="h-full w-full object-cover">
                                                        @else
                                                            {{ str($loan->bookCopy?->book?->title ?? 'BK')->words(1, '')->substr(0, 2)->upper() }}
                                                        @endif
                                                    </div>
                                                    <div class="min-w-0">
                                                        <a href="{{ route('books.show', $loan->bookCopy->book) }}" class="font-semibold text-zinc-950 transition hover:text-emerald-700 dark:text-white dark:hover:text-emerald-300">
                                                            {{ $loan->bookCopy?->book?->title ?? '-' }}
                                                        </a>
                                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $loan->bookCopy?->book?->isbn ?? '-' }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $loan->user?->name ?? '-' }}</div>
                                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $loan->user?->membership_number ?? '-' }}</div>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">
                                                {{ $loan->borrowed_at?->format('Y-m-d') ?? '-' }}
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">
                                                <div>{{ $loan->due_at?->format('Y-m-d') ?? 'Be termino' }}</div>
                                                @if($loan->is_overdue)
                                                    <div class="mt-1 text-xs font-semibold text-red-600 dark:text-red-300">
                                                        ({{ $loan->overdue_days }} d. vėluoja)
                                                    </div>
                                                @elseif($daysUntilDue !== null)
                                                    <div class="mt-1 text-xs font-semibold text-emerald-600 dark:text-emerald-300">
                                                        ({{ max($daysUntilDue, 0) }} d. liko)
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 align-middle">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusMeta['classes'] }}">
                                                    {{ $statusMeta['label'] }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $loan->bookCopy?->inventory_code ?? '-' }}</div>
                                            </td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex items-center gap-3 text-zinc-500 dark:text-zinc-400">
                                                    <a href="{{ route('books.show', $loan->bookCopy->book) }}" title="Peržiūrėti knygą" aria-label="Peržiūrėti knygą" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:text-white">
                                                        <flux:icon.eye class="size-4" />
                                                    </a>

                                                    @if(in_array($loan->status, ['aktyvi', 'vėluoja'], true))
                                                        <form method="POST" action="{{ route('loans.return', $loan->bookCopy) }}">
                                                            @csrf
                                                            <button type="submit" title="Grąžinti kopiją" aria-label="Grąžinti kopiją" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:text-white">
                                                                <flux:icon.arrow-uturn-left class="size-4" />
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-zinc-200 px-5 py-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
                            <div>Rodoma {{ $loans->firstItem() }}-{{ $loans->lastItem() }} is {{ $loans->total() }}</div>
                            <div>{{ $loans->links() }}</div>
                        </div>
                    @else
                        <div class="p-6">
                            <x-ui.empty-state
                                title="Išduotų knygų nerasta"
                                description="Pabandyk pakeisti paiešką arba filtrus."
                            />
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>







