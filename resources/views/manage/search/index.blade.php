<x-layouts::app :title="'Valdymo paieska'">
    <x-ui.page>
        <x-ui.page-header
            eyebrow="Valdymas"
            title="Globali paieska"
            description="Greitai rask vartotojus, autorius, filialus, vietas ir kitus valdymo irasus."
        />

        <x-ui.panel class="mb-6" title="Paieska" description="Ivesk varda, koda, ISBN ar kita rakta, pagal kuri nori ieskoti.">
            <form method="GET" action="{{ route('manage.search.index') }}" class="grid gap-4 md:grid-cols-[1fr_auto_auto]">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    class="app-input"
                    placeholder="Pvz. vartotojas, knyga, filialas, vieta..."
                >

                <button type="submit" class="app-button-primary">Ieskoti</button>
                <a href="{{ route('manage.search.index') }}" class="app-button-secondary">Isvalyti</a>
            </form>
        </x-ui.panel>

        @if ($search === '')
            <x-ui.panel>
                <x-ui.empty-state
                    title="Ivesk paieskos fraze"
                    description="Rezultatai atsiras cia, kai pradesi ieskoti valdymo irasu."
                />
            </x-ui.panel>
        @elseif ($totalResults === 0)
            <x-ui.panel>
                <x-ui.empty-state
                    title="Nieko nerasta"
                    description="Pabandyk trumpesne arba bendresne paieska."
                />
            </x-ui.panel>
        @else
            <div class="grid gap-6 xl:grid-cols-2">
                <x-ui.panel title="Vartotojai" :description="'Rasta: '.$results['users']->count()">
                    @if ($results['users']->isEmpty())
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Atitikmenu nera.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($results['users'] as $user)
                                <a href="{{ route('manage.users.show', $user) }}" class="app-muted-card block transition hover:border-teal-300 hover:bg-teal-50/60 dark:hover:border-teal-700 dark:hover:bg-teal-500/10">
                                    <div class="font-semibold text-zinc-950 dark:text-white">{{ $user->name }}</div>
                                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $user->email }}</div>
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $user->membership_number ?: strtoupper($user->role) }}
                                        @if ($user->library)
                                            • {{ $user->library->name }}
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </x-ui.panel>

                <x-ui.panel title="Autoriai" :description="'Rasta: '.$results['authors']->count()">
                    @if ($results['authors']->isEmpty())
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Atitikmenu nera.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($results['authors'] as $author)
                                <a href="{{ route('manage.authors.edit', $author) }}" class="app-muted-card block transition hover:border-teal-300 hover:bg-teal-50/60 dark:hover:border-teal-700 dark:hover:bg-teal-500/10">
                                    <div class="font-semibold text-zinc-950 dark:text-white">{{ $author->name }}</div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </x-ui.panel>

                <x-ui.panel title="Filialai" :description="'Rasta: '.$results['branches']->count()">
                    @if ($results['branches']->isEmpty())
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Atitikmenu nera.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($results['branches'] as $branch)
                                <a href="{{ route('manage.branches.edit', $branch) }}" class="app-muted-card block transition hover:border-teal-300 hover:bg-teal-50/60 dark:hover:border-teal-700 dark:hover:bg-teal-500/10">
                                    <div class="font-semibold text-zinc-950 dark:text-white">{{ $branch->name }}</div>
                                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $branch->code ?: '-' }}</div>
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $branch->city ?: '-' }}
                                        @if ($branch->library)
                                            • {{ $branch->library->name }}
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </x-ui.panel>

                <x-ui.panel title="Vietos" :description="'Rasta: '.$results['locations']->count()">
                    @if ($results['locations']->isEmpty())
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Atitikmenu nera.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($results['locations'] as $location)
                                <a href="{{ route('manage.locations.edit', $location) }}" class="app-muted-card block transition hover:border-teal-300 hover:bg-teal-50/60 dark:hover:border-teal-700 dark:hover:bg-teal-500/10">
                                    <div class="font-semibold text-zinc-950 dark:text-white">{{ $location->name }}</div>
                                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ collect([$location->room, $location->shelf])->filter()->join(' / ') ?: ($location->code ?: '-') }}
                                    </div>
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $location->branch?->name ?: '-' }}
                                        @if ($location->library)
                                            • {{ $location->library->name }}
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </x-ui.panel>

                @if (auth()->user()?->isSuperAdmin())
                    <x-ui.panel title="Knygos" :description="'Rasta: '.$results['books']->count()">
                        @if ($results['books']->isEmpty())
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Atitikmenu nera.</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($results['books'] as $book)
                                    <a href="{{ route('manage.books.edit', $book) }}" class="app-muted-card block transition hover:border-teal-300 hover:bg-teal-50/60 dark:hover:border-teal-700 dark:hover:bg-teal-500/10">
                                        <div class="font-semibold text-zinc-950 dark:text-white">{{ $book->title }}</div>
                                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $book->isbn ?: '-' }}</div>
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $book->authors->pluck('name')->join(', ') ?: '-' }}
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </x-ui.panel>

                    <x-ui.panel title="Kategorijos" :description="'Rasta: '.$results['categories']->count()">
                        @if ($results['categories']->isEmpty())
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Atitikmenu nera.</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($results['categories'] as $category)
                                    <a href="{{ route('manage.categories.edit', $category) }}" class="app-muted-card block transition hover:border-teal-300 hover:bg-teal-50/60 dark:hover:border-teal-700 dark:hover:bg-teal-500/10">
                                        <div class="font-semibold text-zinc-950 dark:text-white">{{ $category->name }}</div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </x-ui.panel>

                    <x-ui.panel title="Leidyklos" :description="'Rasta: '.$results['publishers']->count()">
                        @if ($results['publishers']->isEmpty())
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Atitikmenu nera.</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($results['publishers'] as $publisher)
                                    <a href="{{ route('manage.publishers.edit', $publisher) }}" class="app-muted-card block transition hover:border-teal-300 hover:bg-teal-50/60 dark:hover:border-teal-700 dark:hover:bg-teal-500/10">
                                        <div class="font-semibold text-zinc-950 dark:text-white">{{ $publisher->name }}</div>
                                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $publisher->country ?: '-' }}</div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </x-ui.panel>
                @endif
            </div>
        @endif
    </x-ui.page>
</x-layouts::app>
