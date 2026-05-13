<x-layouts::app :title="'Bibliotekos'">
    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1500px] space-y-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">Bibliotekos</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Tvarkykite bibliotekas, jų viešumą ir priskirtus darbuotojus.</p>
                    </div>
                    <a href="{{ route('manage.libraries.create') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                        <flux:icon.plus class="size-4" />
                        Pridėti biblioteką
                    </a>
                </div>

                @if(session('success'))
                    <x-ui.alert>{{ session('success') }}</x-ui.alert>
                @endif

                @if(session('error'))
                    <x-ui.alert type="error">{{ session('error') }}</x-ui.alert>
                @endif

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="px-5 py-4">
                        <form method="GET" action="{{ route('manage.libraries.index') }}" class="grid gap-3 xl:grid-cols-[minmax(320px,1.5fr)_auto_auto] xl:items-center">
                            <div class="relative xl:min-w-0">
                                <input type="text" name="search" value="{{ $search }}" placeholder="Ieškoti bibliotekos pagal pavadinimą, kodą ar miestą..." class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 pl-11 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400">
                                    <flux:icon.magnifying-glass class="size-4" />
                                </div>
                            </div>
                            <button type="submit" class="app-button-secondary h-11 rounded-2xl px-4">
                                <flux:icon.funnel class="mr-2 size-4" />
                                Filtruoti
                            </button>
                            <a href="{{ route('manage.libraries.index') }}" class="app-button-secondary h-11 rounded-2xl px-4">Išvalyti</a>
                        </form>
                    </div>
                </section>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    @if($libraries->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                <thead class="bg-zinc-50/80 dark:bg-zinc-950/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Biblioteka</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Kontaktai</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Filialai</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Egzemplioriai</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Darbuotojai</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Būsena</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Veiksmai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @foreach($libraries as $library)
                                        <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                            <td class="px-4 py-4 align-middle">
                                                <div class="font-semibold text-zinc-950 dark:text-white">{{ $library->name }}</div>
                                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $library->code }} @if($library->city) · {{ $library->city }} @endif</div>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">
                                                <div>{{ $library->email ?: '-' }}</div>
                                                <div class="text-zinc-500 dark:text-zinc-400">{{ $library->phone ?: '-' }}</div>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-center text-sm font-semibold text-zinc-950 dark:text-white">{{ $library->branches_count }}</td>
                                            <td class="px-4 py-4 align-middle text-center text-sm font-semibold text-zinc-950 dark:text-white">{{ $library->book_copies_count }}</td>
                                            <td class="px-4 py-4 align-middle text-center text-sm font-semibold text-zinc-950 dark:text-white">{{ $library->staff_users_count }}</td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex flex-wrap gap-2">
                                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $library->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' }}">
                                                        {{ $library->is_active ? 'Aktyvi' : 'Neaktyvi' }}
                                                    </span>
                                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $library->is_public ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' }}">
                                                        {{ $library->is_public ? 'Vieša' : 'Privati' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex items-center gap-3 text-zinc-500 dark:text-zinc-400">
                                                    <a href="{{ route('manage.libraries.edit', $library) }}" title="Redaguoti biblioteką" aria-label="Redaguoti biblioteką" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:text-white">
                                                        <flux:icon.pencil-square class="size-4" />
                                                    </a>
                                                    <form method="POST" action="{{ route('manage.libraries.destroy', $library) }}" onsubmit="return confirm('Ištrinti šią biblioteką?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" title="Ištrinti biblioteką" aria-label="Ištrinti biblioteką" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-red-200 bg-white text-red-600 transition hover:bg-red-50 dark:border-red-900/60 dark:bg-zinc-900 dark:text-red-300 dark:hover:bg-red-950/30">
                                                            <flux:icon.trash class="size-4" />
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            {{ $libraries->links() }}
                        </div>
                    @else
                        <x-ui.empty-state title="Bibliotekų nerasta" description="Pakeiskite paiešką arba sukurkite naują biblioteką." />
                    @endif
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>







