<x-layouts::app :title="__('Rezervacijos')">
    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1500px] space-y-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">Rezervacijos</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            Perziurekite ir tvarkykite knygu rezervacijas
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('exports.list', array_merge(request()->query(), ['resource' => 'reservations'])) }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.arrow-down-tray class="size-4" />
                            Eksportuoti
                        </a>

                        <button type="button" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.funnel class="size-4" />
                            Filtruoti
                        </button>
                    </div>
                </div>

                @if (session('success'))
                    <x-ui.alert type="success">
                        {{ session('success') }}
                    </x-ui.alert>
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

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                <flux:icon.calendar-days class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Visos rezervacijos</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['all_count'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Is viso</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                                <flux:icon.list-bullet class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Aktyvios</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['active_count'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Laukia ivykdymo</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <flux:icon.check-circle class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Ivykdytos</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['fulfilled_count'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Perduota vartotojui</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300">
                                <flux:icon.x-circle class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Atsauktos</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['cancelled_count'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Atsauktos vartotojo</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300">
                                <flux:icon.clock class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Pasibaigusios</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $summary['expired_count'] }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Nepaimtos</div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="px-5 py-4">
                        <form method="GET" action="{{ route('reservations.index') }}" class="grid gap-3 xl:grid-cols-[minmax(320px,1.5fr)_170px_210px_170px_auto] xl:items-center">
                            <div class="relative xl:min-w-0">
                                <input
                                    id="search"
                                    type="text"
                                    name="search"
                                    value="{{ request('search') }}"
                                    placeholder="Ieskoti pagal knygos pavadinima, vartotojo varda, el. pasta..."
                                    class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 pl-11 shadow-none dark:border-zinc-700 dark:bg-zinc-950"
                                >
                                <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400">
                                    <flux:icon.magnifying-glass class="size-4" />
                                </div>
                            </div>

                            <div class="xl:min-w-0">
                                <select id="status" name="status" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Busena</option>
                                    <option value="reserved" {{ request('status') === 'reserved' ? 'selected' : '' }}>Aktyvios</option>
                                    <option value="fulfilled" {{ request('status') === 'fulfilled' ? 'selected' : '' }}>Ivykdytos</option>
                                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Atsauktos</option>
                                    <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Pasibaigusios</option>
                                </select>
                            </div>

                            <div class="xl:min-w-0">
                                <input type="date" id="reservation_date" name="reservation_date" value="{{ request('reservation_date') }}" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                            </div>

                            @if(auth()->user()?->isSuperAdmin())
                                <div class="xl:min-w-0">
                                    <select id="library_id" name="library_id" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                        <option value="">Filialas</option>
                                        @foreach($libraries as $library)
                                            <option value="{{ $library->id }}" {{ (string) request('library_id') === (string) $library->id ? 'selected' : '' }}>
                                                {{ $library->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                <input type="hidden" name="library_id" value="{{ request('library_id') }}">
                            @endif

                            <div class="flex items-center gap-3 xl:justify-start">
                                <button type="submit" class="app-button-secondary h-11 rounded-2xl px-4">
                                    <flux:icon.funnel class="mr-2 size-4" />
                                    Filtruoti
                                </button>

                                <a href="{{ route('reservations.index') }}" class="app-button-secondary h-11 rounded-2xl px-4">
                                    Isvalyti
                                </a>
                            </div>

                            <input type="hidden" name="per_page" value="{{ request('per_page', 20) }}">
                        </form>
                    </div>
                </section>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    @if ($reservations->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                <thead class="bg-zinc-50/80 dark:bg-zinc-950/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left">
                                            <input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500">
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Knyga</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Vartotojas</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Rezervacijos data</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Busena</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Galioja iki</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Eiles nr.</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Veiksmai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @foreach ($reservations as $reservation)
                                        @php
                                            $statusMeta = match ($reservation->status) {
                                                'reserved' => ['label' => 'Aktyvi', 'classes' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'],
                                                'fulfilled' => ['label' => 'Ivykdyta', 'classes' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'],
                                                'cancelled' => ['label' => 'Atsaukta', 'classes' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'],
                                                'expired' => ['label' => 'Pasibaigusi', 'classes' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'],
                                                default => ['label' => ucfirst((string) $reservation->status), 'classes' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'],
                                            };
                                            $daysUntilExpiry = $reservation->expires_at ? now()->startOfDay()->diffInDays($reservation->expires_at->copy()->startOfDay(), false) : null;
                                        @endphp
                                        <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                            <td class="px-4 py-4 align-middle">
                                                <input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500">
                                            </td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex items-start gap-3">
                                                    <div class="flex h-14 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md border border-zinc-200 bg-zinc-100 text-[10px] font-semibold uppercase text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                                        {{ str($reservation->book?->title ?? 'BK')->words(1, '')->substr(0, 2)->upper() }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <a href="{{ route('books.show', $reservation->book) }}" class="font-semibold text-zinc-950 transition hover:text-emerald-700 dark:text-white dark:hover:text-emerald-300">
                                                            {{ $reservation->book?->title ?? 'Nezinoma knyga' }}
                                                        </a>
                                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $reservation->book?->isbn ?? '-' }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $reservation->user?->name ?? '-' }}</div>
                                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $reservation->user?->email ?? $reservation->user?->membership_number ?? '-' }}</div>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">
                                                {{ $reservation->reserved_at?->format('Y-m-d H:i') ?? '-' }}
                                            </td>
                                            <td class="px-4 py-4 align-middle">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusMeta['classes'] }}">
                                                    {{ $statusMeta['label'] }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">
                                                @if($reservation->expires_at)
                                                    <div>{{ $reservation->expires_at->format('Y-m-d') }}</div>
                                                    <div class="mt-1 text-xs font-semibold {{ ($daysUntilExpiry !== null && $daysUntilExpiry < 0) ? 'text-red-600 dark:text-red-300' : 'text-emerald-600 dark:text-emerald-300' }}">
                                                        @if($daysUntilExpiry !== null && $daysUntilExpiry >= 0)
                                                            ({{ $daysUntilExpiry }} d.)
                                                        @elseif($daysUntilExpiry !== null)
                                                            (pasibaige)
                                                        @endif
                                                    </div>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 align-middle text-center text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                                @if($reservation->isPending() && $reservation->queue_position)
                                                    {{ $reservation->queue_position }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex items-center gap-3 text-zinc-500 dark:text-zinc-400">
                                                    <a href="{{ route('books.show', $reservation->book) }}" title="Perziureti knyga" aria-label="Perziureti knyga" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:text-white">
                                                        <flux:icon.eye class="size-4" />
                                                    </a>

                                                    @if($reservation->isActive())
                                                        <livewire:reservations.cancel-reservation-form
                                                            :reservation="$reservation"
                                                            :compact="true"
                                                            :key="'reservation-index-cancel-'.$reservation->id"
                                                        />
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-zinc-200 px-5 py-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
                            <div>Rodoma {{ $reservations->firstItem() }}-{{ $reservations->lastItem() }} is {{ $reservations->total() }}</div>
                            <div>{{ $reservations->links() }}</div>
                        </div>
                    @else
                        <div class="p-6">
                            <x-ui.empty-state
                                title="Rezervaciju nerasta"
                                description="Pabandyk pakeisti paieska arba filtrus."
                            />
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>
