<x-layouts::app :title="'Mano paskyra'">
    <x-ui.page>
        <x-ui.page-header
            eyebrow="Nario zona"
            title="Mano paskyra"
            description="Svarbiausia informacija apie tavo paskyra, isduotas knygas, rezervacijas ir bibliotekos naujienas."
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="app-card">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Aktyvios isduotos knygos</p>
                <div class="mt-3 text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ $activeLoansCount }}</div>
            </div>

            <div class="app-card">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Aktyvios rezervacijos</p>
                <div class="mt-3 text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ $activeReservationsCount }}</div>
            </div>

            <div class="app-card">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Veluojancios knygos</p>
                <div class="mt-3 text-4xl font-bold tracking-tight text-red-600 dark:text-red-300">{{ $overdueLoansCount }}</div>
            </div>

            <div class="app-card">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Nauji pranesimai</p>
                <div class="mt-3 text-4xl font-bold tracking-tight text-amber-600 dark:text-amber-300">{{ $unreadNotificationsCount }}</div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <x-ui.panel title="Profilis" description="Tavo narystes informacija ir bibliotekos kontaktai.">
                <div class="space-y-4">
                    <div class="app-muted-card">
                        <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $member->name }}</div>
                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $member->email }}</div>
                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $member->phone ?: 'Telefono numeris nenurodytas' }}</div>
                    </div>

                    <div class="app-muted-card">
                        <div class="app-label">Nario numeris</div>
                        <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $member->membership_number ?: '-' }}</div>
                    </div>

                    <div class="app-muted-card">
                        <div class="app-label">Biblioteka</div>
                        <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $member->library?->name ?: '-' }}</div>
                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ collect([$member->library?->address, $member->library?->city])->filter()->join(', ') ?: 'Adresas nenurodytas' }}
                        </div>
                    </div>

                    <a href="{{ route('account.profile') }}" class="app-button-secondary w-full">Atidaryti profili</a>
                </div>
            </x-ui.panel>

            <x-ui.panel title="Mano isduotos knygos" description="Tavo siuo metu paimtos knygos.">
                @if($activeLoans->isEmpty())
                    <x-ui.empty-state title="Aktyviu isdavimu nera" description="Kai pasiimsi knyga, ji atsiras cia." />
                @else
                    <div class="space-y-3">
                        @foreach($activeLoans as $loan)
                            <div class="app-muted-card">
                                <a href="{{ route('books.show', $loan->bookCopy?->book_id) }}" class="text-sm font-semibold text-zinc-950 hover:text-teal-700 dark:text-white dark:hover:text-teal-300">
                                    {{ $loan->bookCopy?->book?->title ?: 'Nezinoma knyga' }}
                                </a>
                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $loan->bookCopy?->book?->isbn ?: '-' }}
                                </div>
                                <div class="mt-3 text-sm text-zinc-700 dark:text-zinc-300">
                                    Grazinti iki: {{ $loan->due_at?->format('Y-m-d') ?: 'Be termino' }}
                                </div>
                                @if($loan->is_overdue)
                                    <div class="mt-1 text-sm font-semibold text-red-600 dark:text-red-400">Veluoja {{ $loan->overdue_days }} d.</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4">
                    <a href="{{ route('loans.index') }}" class="app-button-secondary w-full">Visos mano isduotos knygos</a>
                </div>
            </x-ui.panel>

            <x-ui.panel title="Mano rezervacijos" description="Aktyvios rezervacijos ir ju eiga.">
                @if($activeReservations->isEmpty())
                    <x-ui.empty-state title="Rezervaciju nera" description="Kai rezervuosi knyga, jos bus matomos cia." />
                @else
                    <div class="space-y-3">
                        @foreach($activeReservations as $reservation)
                            <div class="app-muted-card">
                                <a href="{{ route('books.show', $reservation->book_id) }}" class="text-sm font-semibold text-zinc-950 hover:text-teal-700 dark:text-white dark:hover:text-teal-300">
                                    {{ $reservation->book?->title ?: 'Nezinoma knyga' }}
                                </a>
                                <div class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                                    Statusas:
                                    <span class="font-medium text-zinc-950 dark:text-white">
                                        {{ $reservation->isCurrent() ? 'Paruosta atsiimti' : ($reservation->isPending() ? 'Laukia eileje' : ucfirst($reservation->status)) }}
                                    </span>
                                </div>
                                <div class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ $reservation->expires_at?->format('Y-m-d H:i') ?: 'Terminas dar nepriskirtas' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4">
                    <a href="{{ route('reservations.index') }}" class="app-button-secondary w-full">Visos mano rezervacijos</a>
                </div>
            </x-ui.panel>
        </div>

        <x-ui.panel class="mt-6" title="Naujausi pranesimai" description="Svarbiausi paskyros ir bibliotekos pranesimai.">
            @if($recentNotifications->isEmpty())
                <x-ui.empty-state title="Pranesimu nera" description="Kai atsiras nauju pranesimu, jie bus rodomi cia." />
            @else
                <div class="space-y-3">
                    @foreach($recentNotifications as $notification)
                        <div class="app-muted-card">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $notification->title }}</div>
                                    <div class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $notification->message }}</div>
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $notification->created_at?->format('Y-m-d H:i') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-4">
                <a href="{{ route('notifications.index') }}" class="app-button-secondary w-full">Visi pranesimai</a>
            </div>
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>
