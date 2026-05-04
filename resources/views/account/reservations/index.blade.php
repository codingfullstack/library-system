<x-layouts::app :title="'Mano rezervacijos'">
    <x-ui.page>
        <x-ui.page-header
            eyebrow="Nario zona"
            title="Mano rezervacijos"
            description="Matai savo rezervaciju busena, vieta eileje ir gali atsaukti aktyvia rezervacija."
        />

        <x-ui.panel class="mb-6" title="Paieska" description="Greitai rask rezervuota knyga.">
            <form method="GET" action="{{ route('reservations.index') }}" class="grid gap-4 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label for="search" class="app-label">Paieska</label>
                    <input id="search" type="text" name="search" value="{{ request('search') }}" class="app-input" placeholder="Knygos pavadinimas ar ISBN">
                </div>

                <div>
                    <label for="status" class="app-label">Statusas</label>
                    <select id="status" name="status" class="app-input">
                        <option value="">Visi statusai</option>
                        <option value="reserved" @selected(request('status') === 'reserved')>Aktyvios</option>
                        <option value="fulfilled" @selected(request('status') === 'fulfilled')>Ivykdytos</option>
                        <option value="cancelled" @selected(request('status') === 'cancelled')>Atsauktos</option>
                        <option value="expired" @selected(request('status') === 'expired')>Pasibaigusios</option>
                    </select>
                </div>

                <div>
                    <label for="per_page" class="app-label">Rodyti po</label>
                    <select id="per_page" name="per_page" class="app-input">
                        <option value="10" @selected(request('per_page') == 10)>10</option>
                        <option value="15" @selected(request('per_page', 15) == 15)>15</option>
                        <option value="25" @selected(request('per_page') == 25)>25</option>
                    </select>
                </div>

                <div class="md:col-span-4 flex flex-col gap-2 sm:flex-row">
                    <button type="submit" class="app-button-primary">Filtruoti</button>
                    <a href="{{ route('reservations.index') }}" class="app-button-secondary">Isvalyti</a>
                </div>
            </form>
        </x-ui.panel>

        <x-ui.panel body-class="p-0">
            @if($reservations->count())
                <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach($reservations as $reservation)
                        @php
                            $statusLabel = match (true) {
                                $reservation->isCurrent() => 'Paruosta atsiimti',
                                $reservation->isPending() => 'Laukia eileje',
                                $reservation->status === 'fulfilled' || ! is_null($reservation->fulfilled_at) => 'Ivykdyta',
                                $reservation->status === 'cancelled' || ! is_null($reservation->cancelled_at) => 'Atsaukta',
                                $reservation->status === 'expired' => 'Pasibaigusi',
                                default => $reservation->status,
                            };
                        @endphp
                        <article class="px-5 py-5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2">
                                    <a href="{{ route('books.show', $reservation->book_id) }}" class="text-lg font-semibold text-zinc-950 hover:text-teal-700 dark:text-white dark:hover:text-teal-300">
                                        {{ $reservation->book?->title ?: 'Nezinoma knyga' }}
                                    </a>
                                    <div class="text-sm text-zinc-500 dark:text-zinc-400">ISBN: {{ $reservation->book?->isbn ?: '-' }}</div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-300">Biblioteka: {{ $reservation->library?->name ?: '-' }}</div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <x-ui.status-badge :status="$reservation->status" :label="$statusLabel" />
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                <div class="app-muted-card">
                                    <div class="app-label">Vieta eileje</div>
                                    <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">
                                        {{ $reservation->isPending() && $reservation->queue_position ? '#' . $reservation->queue_position : '-' }}
                                    </div>
                                </div>
                                <div class="app-muted-card">
                                    <div class="app-label">Rezervuota</div>
                                    <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $reservation->reserved_at?->format('Y-m-d H:i') ?: '-' }}</div>
                                </div>
                                <div class="app-muted-card">
                                    <div class="app-label">Galioja iki</div>
                                    <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $reservation->expires_at?->format('Y-m-d H:i') ?: 'Terminas dar nepriskirtas' }}</div>
                                </div>
                            </div>

                            @if($reservation->notes)
                                <div class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-950/40 dark:text-zinc-300">
                                    {{ $reservation->notes }}
                                </div>
                            @endif

                            @if($reservation->isPending())
                                <div class="mt-4">
                                    <livewire:reservations.cancel-reservation-form :reservation="$reservation" :key="'member-reservation-cancel-'.$reservation->id" />
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    {{ $reservations->links() }}
                </div>
            @else
                <div class="p-5">
                    <x-ui.empty-state title="Rezervaciju nerasta" description="Kai rezervuosi knyga, ji atsiras siame sarase." />
                </div>
            @endif
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>
