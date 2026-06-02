<x-layouts::app :title="'Mano paskyra'">
    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1500px] space-y-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">Mano paskyra</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Svarbiausia informacija apie tavo išduotas knygas, rezervacijas ir pranešimus.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('books.index') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.book-open class="size-4" />
                            Knygų katalogas
                        </a>
                        <a href="{{ route('account.profile') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                            <flux:icon.user-circle class="size-4" />
                            Profilis
                        </a>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">
                                <flux:icon.book-open-text class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Aktyvios išduotos knygos</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $activeLoansCount }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Aktyvios</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <flux:icon.bookmark-square class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Rezervacijos</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $activeReservationsCount }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Aktyvios</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300">
                                <flux:icon.exclamation-circle class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Vėluoja</div>
                                <div class="mt-1 text-3xl font-bold text-red-600 dark:text-red-300">{{ $overdueLoansCount }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Reikia dėmesio</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                <flux:icon.bell class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Pranešimai</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $unreadNotificationsCount }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Neperskaityti</div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="grid gap-6 xl:grid-cols-3">
                    <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Greiti veiksmai</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Dažniausiai naudojamos paskyros nuorodos.</p>
                        </div>
                        <div class="space-y-3 p-5">
                            <a href="{{ route('books.index') }}" class="flex items-center justify-between rounded-lg bg-zinc-50 p-3 text-sm font-semibold text-zinc-950 ring-1 ring-zinc-200 transition hover:bg-zinc-100 dark:bg-zinc-950/50 dark:text-white dark:ring-zinc-800 dark:hover:bg-zinc-800">
                                <span class="inline-flex items-center gap-2">
                                    <flux:icon.book-open class="size-4 text-emerald-700 dark:text-emerald-300" />
                                    Knygų katalogas
                                </span>
                                <flux:icon.arrow-right class="size-4 text-zinc-400" />
                            </a>
                            <a href="{{ route('loans.index') }}" class="flex items-center justify-between rounded-lg bg-zinc-50 p-3 text-sm font-semibold text-zinc-950 ring-1 ring-zinc-200 transition hover:bg-zinc-100 dark:bg-zinc-950/50 dark:text-white dark:ring-zinc-800 dark:hover:bg-zinc-800">
                                <span class="inline-flex items-center gap-2">
                                    <flux:icon.book-open-text class="size-4 text-blue-700 dark:text-blue-300" />
                                    Mano išduotos knygos
                                </span>
                                <flux:icon.arrow-right class="size-4 text-zinc-400" />
                            </a>
                            <a href="{{ route('reservations.index') }}" class="flex items-center justify-between rounded-lg bg-zinc-50 p-3 text-sm font-semibold text-zinc-950 ring-1 ring-zinc-200 transition hover:bg-zinc-100 dark:bg-zinc-950/50 dark:text-white dark:ring-zinc-800 dark:hover:bg-zinc-800">
                                <span class="inline-flex items-center gap-2">
                                    <flux:icon.bookmark-square class="size-4 text-amber-700 dark:text-amber-300" />
                                    Mano rezervacijos
                                </span>
                                <flux:icon.arrow-right class="size-4 text-zinc-400" />
                            </a>
                            <a href="{{ route('account.profile') }}" class="flex items-center justify-between rounded-lg bg-zinc-50 p-3 text-sm font-semibold text-zinc-950 ring-1 ring-zinc-200 transition hover:bg-zinc-100 dark:bg-zinc-950/50 dark:text-white dark:ring-zinc-800 dark:hover:bg-zinc-800">
                                <span class="inline-flex items-center gap-2">
                                    <flux:icon.user class="size-4 text-violet-700 dark:text-violet-300" />
                                    Profilio duomenys
                                </span>
                                <flux:icon.arrow-right class="size-4 text-zinc-400" />
                            </a>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            <div>
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Mano išduotos knygos</h2>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Artimiausi grąžinimo terminai.</p>
                            </div>
                            <a href="{{ route('loans.index') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-600 dark:text-emerald-300">Visos</a>
                        </div>
                        <div class="p-5">
                            @if($activeLoans->isEmpty())
                                <x-ui.empty-state title="Aktyvių išdavimų nėra" description="Kai pasiimsi knygą, ji atsiras čia." />
                            @else
                                <div class="space-y-3">
                                    @foreach($activeLoans as $loan)
                                        <div class="rounded-lg bg-zinc-50 p-3 ring-1 ring-zinc-200 dark:bg-zinc-950/50 dark:ring-zinc-800">
                                            <a href="{{ route('books.show', $loan->bookCopy?->book_id) }}" class="text-sm font-semibold text-zinc-950 hover:text-emerald-700 dark:text-white dark:hover:text-emerald-300">
                                                {{ $loan->bookCopy?->book?->title ?: 'Nežinoma knyga' }}
                                            </a>
                                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                                <span>ISBN: {{ $loan->bookCopy?->book?->isbn ?: '-' }}</span>
                                                <span>Grąžinti iki: {{ $loan->due_at?->format('Y-m-d') ?: 'Be termino' }}</span>
                                            </div>
                                            @if($loan->is_overdue)
                                                <div class="mt-2 text-sm font-semibold text-red-600 dark:text-red-400">Vėluoja {{ $loan->overdue_days }} d.</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            <div>
                                <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Mano rezervacijos</h2>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Aktyvios rezervacijos ir eilė.</p>
                            </div>
                            <a href="{{ route('reservations.index') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-600 dark:text-emerald-300">Visos</a>
                        </div>
                        <div class="p-5">
                            @if($activeReservations->isEmpty())
                                <x-ui.empty-state title="Rezervacijų nėra" description="Kai rezervuosi knygą, ji bus matoma čia." />
                            @else
                                <div class="space-y-3">
                                    @foreach($activeReservations as $reservation)
                                        <div class="rounded-lg bg-zinc-50 p-3 ring-1 ring-zinc-200 dark:bg-zinc-950/50 dark:ring-zinc-800">
                                            <a href="{{ route('books.show', $reservation->book_id) }}" class="text-sm font-semibold text-zinc-950 hover:text-emerald-700 dark:text-white dark:hover:text-emerald-300">
                                                {{ $reservation->book?->title ?: 'Nežinoma knyga' }}
                                            </a>
                                            <div class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                                                {{ $reservation->isCurrent() ? 'Paruošta atsiimti' : ($reservation->isPending() ? 'Laukia eilėje' : ucfirst($reservation->status)) }}
                                            </div>
                                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $reservation->expires_at?->format('Y-m-d H:i') ?: 'Terminas dar nepriskirtas' }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>
                </div>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <div>
                            <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Naujausi pranešimai</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Svarbiausi paskyros ir bibliotekos pranešimai.</p>
                        </div>
                        <a href="{{ route('notifications.index') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-600 dark:text-emerald-300">Visi</a>
                    </div>
                    <div class="p-5">
                        @if($recentNotifications->isEmpty())
                            <x-ui.empty-state title="Pranešimų nėra" description="Kai atsiras naujų pranešimų, jie bus rodomi čia." />
                        @else
                            <div class="space-y-3">
                                @foreach($recentNotifications as $notification)
                                    @php
                                        $data = $notification->data ?? [];
                                        $title = $data['title'] ?? 'Naujas pranešimas';
                                        $message = $data['body'] ?? $data['message'] ?? '';
                                        $url = $data['url'] ?? route('notifications.index');
                                    @endphp
                                    <div class="rounded-lg bg-zinc-50 p-3 ring-1 ring-zinc-200 dark:bg-zinc-950/50 dark:ring-zinc-800">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <a href="{{ $url }}" class="text-sm font-semibold text-zinc-950 transition hover:text-emerald-700 dark:text-white dark:hover:text-emerald-300">
                                                    {{ $title }}
                                                </a>
                                                @if($message !== '')
                                                    <div class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $message }}</div>
                                                @endif
                                            </div>
                                            <div class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">{{ $notification->created_at?->format('Y-m-d H:i') }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>







