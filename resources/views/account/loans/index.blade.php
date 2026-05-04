<x-layouts::app :title="'Mano isduotos knygos'">
    <x-ui.page>
        <x-ui.page-header
            eyebrow="Nario zona"
            title="Mano isduotos knygos"
            description="Visos siuo metu tavo paimtos knygos ir ju grazinimo terminai."
        />

        <x-ui.panel class="mb-6" title="Paieska" description="Greitai rask norima knyga savo aktyviuose isdavimuose.">
            <form method="GET" action="{{ route('loans.index') }}" class="grid gap-4 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label for="search" class="app-label">Paieska</label>
                    <input id="search" type="text" name="search" value="{{ request('search') }}" class="app-input" placeholder="Knyga, ISBN ar kopija">
                </div>

                <div>
                    <label for="status" class="app-label">Busena</label>
                    <select id="status" name="status" class="app-input">
                        <option value="">Visos aktyvios</option>
                        <option value="active" @selected(request('status') === 'active')>Aktyvios</option>
                        <option value="overdue" @selected(request('status') === 'overdue')>Veluojancios</option>
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
                    <a href="{{ route('loans.index') }}" class="app-button-secondary">Isvalyti</a>
                </div>
            </form>
        </x-ui.panel>

        <x-ui.panel body-class="p-0">
            @if($loans->count())
                <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach($loans as $loan)
                        <article class="px-5 py-5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2">
                                    <a href="{{ route('books.show', $loan->bookCopy?->book_id) }}" class="text-lg font-semibold text-zinc-950 hover:text-teal-700 dark:text-white dark:hover:text-teal-300">
                                        {{ $loan->bookCopy?->book?->title ?: 'Nezinoma knyga' }}
                                    </a>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-300">{{ $loan->bookCopy?->book?->subtitle ?: 'Aprasymo papildymo nera.' }}</div>
                                    <div class="text-sm text-zinc-500 dark:text-zinc-400">ISBN: {{ $loan->bookCopy?->book?->isbn ?: '-' }}</div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <x-ui.status-badge :status="$loan->status" :label="$loan->is_overdue ? 'Veluoja' : 'Isduota'" />
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                <div class="app-muted-card">
                                    <div class="app-label">Paimta</div>
                                    <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $loan->borrowed_at?->format('Y-m-d') ?: '-' }}</div>
                                </div>
                                <div class="app-muted-card">
                                    <div class="app-label">Grazinti iki</div>
                                    <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $loan->due_at?->format('Y-m-d') ?: 'Be termino' }}</div>
                                </div>
                                <div class="app-muted-card">
                                    <div class="app-label">Busena</div>
                                    <div class="mt-1 text-sm font-medium {{ $loan->is_overdue ? 'text-red-600 dark:text-red-400' : 'text-zinc-950 dark:text-white' }}">
                                        {{ $loan->is_overdue ? 'Veluoja ' . $loan->overdue_days . ' d.' : 'Terminas dar nepasibaiges' }}
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    {{ $loans->links() }}
                </div>
            @else
                <div class="p-5">
                    <x-ui.empty-state title="Aktyviu isdavimu nera" description="Kai pasiimsi knyga, ji atsiras siame sarase." />
                </div>
            @endif
        </x-ui.panel>
    </x-ui.page>
</x-layouts::app>
