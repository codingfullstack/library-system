<x-layouts::app :title="'Vartotojo perziura'">
    @php
        $roleLabels = [
            'super_admin' => 'Superadmin',
            'admin' => 'Admin',
            'staff' => 'Staff',
            'member' => 'Member',
        ];
    @endphp

    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Vartotojo perziura" :description="$managedUser->name">
            <x-slot:actions>
                <div class="flex flex-col gap-3 sm:flex-row">
                    @if(auth()->id() !== $managedUser->id)
                        <form method="POST" action="{{ route('manage.users.toggle-active', $managedUser) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="app-button-secondary">
                                {{ $managedUser->is_active ? 'Deaktyvuoti' : 'Aktyvuoti' }}
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('manage.users.edit', $managedUser) }}" class="app-button-primary">Redaguoti</a>
                    <a href="{{ route('manage.users.index') }}" class="app-button-secondary">Atgal</a>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        @if(session('success'))
            <x-ui.alert>{{ session('success') }}</x-ui.alert>
        @endif

        @if(session('error'))
            <x-ui.alert type="error">{{ session('error') }}</x-ui.alert>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.panel class="lg:col-span-2" title="Pagrindine informacija" description="Vartotojo duomenys ir role sistemoje.">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="app-muted-card">
                        <dt class="app-label">Vardas</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $managedUser->name }}</dd>
                    </div>

                    <div class="app-muted-card">
                        <dt class="app-label">Role</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $roleLabels[$managedUser->role] ?? $managedUser->role }}</dd>
                    </div>

                    <div class="app-muted-card">
                        <dt class="app-label">El. pastas</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $managedUser->email }}</dd>
                    </div>

                    <div class="app-muted-card">
                        <dt class="app-label">Telefonas</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $managedUser->phone ?: '-' }}</dd>
                    </div>

                    <div class="app-muted-card">
                        <dt class="app-label">Biblioteka</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">
                            {{ $managedUser->library?->name ?: '-' }}
                            @if($managedUser->library?->code)
                                <span class="text-zinc-500 dark:text-zinc-400">({{ $managedUser->library->code }})</span>
                            @endif
                        </dd>
                    </div>

                    <div class="app-muted-card">
                        <dt class="app-label">Nario numeris</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $managedUser->membership_number ?: '-' }}</dd>
                    </div>

                    <div class="app-muted-card">
                        <dt class="app-label">Statusas</dt>
                        <dd class="mt-2">
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium {{ $managedUser->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' }}">
                                {{ $managedUser->is_active ? 'Aktyvus' : 'Neaktyvus' }}
                            </span>
                        </dd>
                    </div>

                    <div class="app-muted-card">
                        <dt class="app-label">Sukurtas</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $managedUser->created_at?->format('Y-m-d H:i') ?: '-' }}</dd>
                    </div>
                </dl>
            </x-ui.panel>

            <x-ui.panel title="Suvestine" description="Svarbiausi susije skaiciai.">
                <div class="grid gap-3">
                    <div class="app-muted-card">
                        <p class="app-label">Aktyviai isduotos knygos</p>
                        <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $managedUser->active_loans_count }}</p>
                    </div>

                    <div class="app-muted-card">
                        <p class="app-label">Visos isduotos knygos</p>
                        <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $managedUser->loans_count }}</p>
                    </div>

                    <div class="app-muted-card">
                        <p class="app-label">Laukiancios rezervacijos</p>
                        <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $managedUser->pending_reservations_count }}</p>
                    </div>

                    <div class="app-muted-card">
                        <p class="app-label">Visos rezervacijos</p>
                        <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $managedUser->reservations_count }}</p>
                    </div>

                    @if($managedUser->role !== 'member')
                        <div class="app-muted-card">
                            <p class="app-label">Isduota knygu</p>
                            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $managedUser->issued_loans_count }}</p>
                        </div>

                        <div class="app-muted-card">
                            <p class="app-label">Priimta knygu</p>
                            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $managedUser->received_loans_count }}</p>
                        </div>
                    @endif
                </div>
            </x-ui.panel>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-ui.panel title="Paskutines isduotos knygos" description="Naujausios su vartotoju susijusios isduotos knygos.">
                @if($recentLoans->count())
                    <div class="space-y-3">
                        @foreach($recentLoans as $loan)
                            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                                <p class="text-sm font-semibold text-zinc-950 dark:text-white">
                                    {{ $loan->bookCopy?->book?->title ?: 'Knyga' }}
                                </p>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    Isduota: {{ $loan->borrowed_at?->format('Y-m-d') ?: '-' }}
                                    - Grazinti iki: {{ $loan->due_at?->format('Y-m-d') ?: '-' }}
                                </p>
                                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                    Statusas: {{ $loan->returned_at ? 'Grazinta' : 'Aktyvi' }}
                                </p>
                            </div>
                        @endforeach
                    </div>

                    @if($recentLoans->hasPages())
                        <div class="pt-3">
                            {{ $recentLoans->links() }}
                        </div>
                    @endif
                @else
                    <x-ui.empty-state title="Isduotu knygu nera" description="Sis vartotojas dar neturi isduotu knygu istorijos." />
                @endif
            </x-ui.panel>

            <x-ui.panel title="Paskutines rezervacijos" description="Naujausios sio vartotojo rezervacijos.">
                @if($recentReservations->count())
                    <div class="space-y-3">
                        @foreach($recentReservations as $reservation)
                            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                                <p class="text-sm font-semibold text-zinc-950 dark:text-white">
                                    {{ $reservation->book?->title ?: 'Knyga' }}
                                </p>
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    Rezervuota: {{ $reservation->reserved_at?->format('Y-m-d H:i') ?: '-' }}
                                </p>
                                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                    Statusas: {{ $reservation->status }}
                                </p>
                            </div>
                        @endforeach
                    </div>

                    @if($recentReservations->hasPages())
                        <div class="pt-3">
                            {{ $recentReservations->links() }}
                        </div>
                    @endif
                @else
                    <x-ui.empty-state title="Rezervaciju nera" description="Sis vartotojas dar neturi rezervaciju istorijos." />
                @endif
            </x-ui.panel>
        </div>

        @if(auth()->user()?->isSuperAdmin())
            <div class="mt-6">
                <x-ui.panel title="Veiksmu istorija" description="Paskutiniai veiksmai, kurie buvo atlikti siam vartotojui.">
                    @include('manage.audit-logs._list', [
                        'auditLogs' => $auditLogs,
                        'emptyTitle' => 'Veiksmu dar nera',
                        'emptyDescription' => 'Siam vartotojui audit irasu dar nesukaupta.',
                    ])
                </x-ui.panel>
            </div>
        @endif
    </x-ui.page>
</x-layouts::app>
