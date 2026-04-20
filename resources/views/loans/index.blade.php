<x-layouts::app :title="__('Paskolos')">
    <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="mb-6">
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">
                Aktyvios paskolos
            </h1>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                Čia gali matyti aktyvias ir vėluojančias bibliotekos paskolas.
            </p>
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ session('success') }}
            </div>
        @endif

        <div class="mb-6 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <form method="GET" action="{{ route('loans.index') }}">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label for="search" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Paieška
                        </label>
                        <input
                            id="search"
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Narys, knyga, ISBN, inventoriaus kodas..."
                            class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white"
                        >
                    </div>

                    <div>
                        <label for="status" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Būsena
                        </label>
                        <select
                            id="status"
                            name="status"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white"
                        >
                            <option value="">Aktyvios + vėluojančios</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktyvios</option>
                            <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Vėluojančios</option>
                            <option value="returned" {{ request('status') === 'returned' ? 'selected' : '' }}>Grąžintos</option>
                        </select>
                    </div>

                    <div>
                        <label for="per_page" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Rodyti po
                        </label>
                        <select
                            id="per_page"
                            name="per_page"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-900 shadow-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white"
                        >
                            <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                            <option value="15" {{ request('per_page', 15) == 15 ? 'selected' : '' }}>15</option>
                            <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex gap-3">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium  shadow-sm transition hover:bg-indigo-500"
                    >
                        Filtruoti
                    </button>

                    <a
                        href="{{ route('loans.index') }}"
                        class="inline-flex items-center justify-center rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                    >
                        Išvalyti
                    </a>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                    <thead class="bg-zinc-50 dark:bg-zinc-950/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Narys</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Knyga</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Kopija</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Būsena</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Grąžinti iki</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Veiksmai</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($loans as $loan)
                            <tr class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                <td class="px-4 py-4 text-sm text-zinc-900 dark:text-white">
                                    <div class="font-semibold">{{ $loan->user?->name }}</div>
                                    <div class="text-zinc-500 dark:text-zinc-400">{{ $loan->user?->membership_number }}</div>
                                </td>

                                <td class="px-4 py-4 text-sm text-zinc-900 dark:text-white">
                                    <div class="font-semibold">{{ $loan->bookCopy?->book?->title }}</div>
                                    <div class="text-zinc-500 dark:text-zinc-400">{{ $loan->bookCopy?->book?->isbn }}</div>
                                </td>

                                <td class="px-4 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ $loan->bookCopy?->inventory_code }}
                                </td>

                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $loan->status === 'overdue' ? 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' }}">
                                        {{ $loan->status }}
                                    </span>
                                </td>

                                <td class="px-4 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ $loan->due_at ?: '-' }}
                                </td>

                                <td class="px-4 py-4">
                                    @if(in_array($loan->status, ['active', 'overdue']))
                                        <form method="POST" action="{{ route('loans.return', $loan->bookCopy) }}">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium  transition hover:bg-indigo-500"
                                            >
                                                Grąžinti
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                    Paskolų nerasta.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">
            {{ $loans->links() }}
        </div>
    </div>
</x-layouts::app>