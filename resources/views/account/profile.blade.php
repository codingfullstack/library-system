<x-layouts::app :title="'Profilis'">
    <x-ui.page>
        <x-ui.page-header
            eyebrow="Nario zona"
            title="Profilis"
            description="Tavo kontaktiniai duomenys, narystes informacija ir bibliotekos kontaktai."
        />

        <div class="grid gap-6 xl:grid-cols-3">
            <x-ui.panel title="Asmenine informacija" description="Pagrindiniai tavo paskyros duomenys.">
                <dl class="space-y-3">
                    <div class="app-muted-card">
                        <dt class="app-label">Vardas</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $member->name }}</dd>
                    </div>
                    <div class="app-muted-card">
                        <dt class="app-label">El. pastas</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $member->email }}</dd>
                    </div>
                    <div class="app-muted-card">
                        <dt class="app-label">Telefonas</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $member->phone ?: '-' }}</dd>
                    </div>
                    <div class="app-muted-card">
                        <dt class="app-label">Nario numeris</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $member->membership_number ?: '-' }}</dd>
                    </div>
                </dl>
            </x-ui.panel>

            <x-ui.panel title="Narystes suvestine" description="Aktyvios tavo paskyros veiklos santrauka.">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="app-muted-card">
                        <div class="app-label">Aktyvios isduotos knygos</div>
                        <div class="mt-2 text-3xl font-bold text-zinc-950 dark:text-white">{{ $activeLoansCount }}</div>
                    </div>
                    <div class="app-muted-card">
                        <div class="app-label">Aktyvios rezervacijos</div>
                        <div class="mt-2 text-3xl font-bold text-zinc-950 dark:text-white">{{ $activeReservationsCount }}</div>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="{{ route('profile.edit') }}" class="app-button-secondary w-full">Redaguoti nustatymuose</a>
                </div>
            </x-ui.panel>

            <x-ui.panel title="Biblioteka" description="Bibliotekos, kuriai esi priskirtas, kontaktai.">
                <dl class="space-y-3">
                    <div class="app-muted-card">
                        <dt class="app-label">Pavadinimas</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $library?->name ?: '-' }}</dd>
                    </div>
                    <div class="app-muted-card">
                        <dt class="app-label">Adresas</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">
                            {{ collect([$library?->address, $library?->city])->filter()->join(', ') ?: '-' }}
                        </dd>
                    </div>
                    <div class="app-muted-card">
                        <dt class="app-label">El. pastas</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $library?->email ?: '-' }}</dd>
                    </div>
                    <div class="app-muted-card">
                        <dt class="app-label">Telefonas</dt>
                        <dd class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $library?->phone ?: '-' }}</dd>
                    </div>
                </dl>
            </x-ui.panel>
        </div>
    </x-ui.page>
</x-layouts::app>
