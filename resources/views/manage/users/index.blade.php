<x-layouts::app :title="'Vartotojai'">
    @php
        $roleLabels = [
            'superadministratorius' => 'Superadministratorius',
            'administratorius' => 'Administratorius',
            'darbuotojas' => 'Darbuotojas',
            'narys' => 'Skaitytojas',
        ];
        $visibleUsers = $users->getCollection();
        $activeUsers = $visibleUsers->where('is_active', true)->count();
        $inactiveUsers = $visibleUsers->where('is_active', false)->count();
        $memberUsers = $visibleUsers->where('role', 'narys')->count();
    @endphp

    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1500px] space-y-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">Vartotojai</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Peržiūrėkite ir tvarkykite bibliotekos vartotojus</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('exports.list', array_merge(request()->query(), ['resource' => 'users'])) }}" class="inline-flex h-11 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <flux:icon.arrow-down-tray class="size-4" />
                            Eksportuoti
                        </a>
                        <a href="{{ route('manage.users.create') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                            <flux:icon.plus class="size-4" />
                            Pridėti vartotoją
                            <flux:icon.chevron-down class="size-4" />
                        </a>
                    </div>
                </div>

                @if(session('success'))
                    <x-ui.alert>{{ session('success') }}</x-ui.alert>
                @endif

                @if(session('error'))
                    <x-ui.alert type="error">{{ session('error') }}</x-ui.alert>
                @endif

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">
                                <flux:icon.users class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Visi vartotojai</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $users->total() }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Iš viso</div>
                            </div>
                        </div>
                    </section>
                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <flux:icon.check-circle class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Aktyvus</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $activeUsers }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Šiame puslapyje</div>
                            </div>
                        </div>
                    </section>
                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300">
                                <flux:icon.x-circle class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Neaktyvūs</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $inactiveUsers }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Šiame puslapyje</div>
                            </div>
                        </div>
                    </section>
                    <section class="rounded-[22px] border border-zinc-200/80 bg-white px-5 py-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                                <flux:icon.user class="size-5" />
                            </span>
                            <div>
                                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Skaitytojai</div>
                                <div class="mt-1 text-3xl font-bold text-zinc-950 dark:text-white">{{ $memberUsers }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Šiame puslapyje</div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="px-5 py-4">
                        <form method="GET" action="{{ route('manage.users.index') }}" class="grid gap-3 xl:grid-cols-[minmax(320px,1.5fr)_180px_180px_auto_auto] xl:items-center">
                            <div class="relative xl:min-w-0">
                                <input type="text" name="search" value="{{ request('search') }}" placeholder="Ieškoti pagal vardą, el. paštą, korteles nr. ar telefona..." class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 pl-11 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400">
                                    <flux:icon.magnifying-glass class="size-4" />
                                </div>
                            </div>
                            <div class="xl:min-w-0">
                                <select name="role" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Tipas</option>
                                    @foreach($manageableRoles as $role)
                                        <option value="{{ $role }}" @selected(request('role') === $role)>{{ $roleLabels[$role] ?? $role }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="xl:min-w-0">
                                <select name="aktyvi" class="app-input h-11 rounded-2xl border-zinc-200 bg-zinc-50 shadow-none dark:border-zinc-700 dark:bg-zinc-950">
                                    <option value="">Statusas</option>
                                    <option value="1" @selected(request('aktyvi') === '1')>Tik aktyvūs</option>
                                    <option value="0" @selected(request('aktyvi') === '0')>Tik neaktyvūs</option>
                                </select>
                            </div>
                            <button type="submit" class="app-button-secondary h-11 rounded-2xl px-4">
                                <flux:icon.funnel class="mr-2 size-4" />
                                Filtruoti
                            </button>
                            <a href="{{ route('manage.users.index') }}" class="app-button-secondary h-11 rounded-2xl px-4">Išvalyti</a>
                        </form>
                    </div>
                </section>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    @if($users->count())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                                <thead class="bg-zinc-50/80 dark:bg-zinc-950/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left"><input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500"></th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Vartotojas</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Korteles nr.</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">El. paštas</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Telefonas</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Tipas</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Filialas</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Statusas</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Veiksmai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @foreach($users as $managedUser)
                                        <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/40">
                                            <td class="px-4 py-4 align-middle"><input type="checkbox" class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500"></td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex items-center gap-3">
                                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-violet-100 text-sm font-semibold text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                                                        {{ $managedUser->initials() }}
                                                    </span>
                                                    <div>
                                                        <div class="font-semibold text-zinc-950 dark:text-white">{{ $managedUser->name }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $managedUser->membership_number ?: '-' }}</td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $managedUser->email }}</td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $managedUser->phone ?: '-' }}</td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $roleLabels[$managedUser->role] ?? $managedUser->role }}</td>
                                            <td class="px-4 py-4 align-middle text-sm text-zinc-700 dark:text-zinc-300">{{ $managedUser->library?->name ?: '-' }}</td>
                                            <td class="px-4 py-4 align-middle">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $managedUser->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' }}">
                                                    {{ $managedUser->is_active ? 'Aktyvus' : 'Neaktyvūs' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 align-middle">
                                                <div class="flex items-center gap-3 text-zinc-500 dark:text-zinc-400">
                                                    <a href="{{ route('manage.users.show', $managedUser) }}" title="Peržiūrėti vartotoją" aria-label="Peržiūrėti vartotoją" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:text-white">
                                                        <flux:icon.eye class="size-4" />
                                                    </a>
                                                    <a href="{{ route('manage.users.edit', $managedUser) }}" title="Redaguoti vartotoją" aria-label="Redaguoti vartotoją" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:text-white">
                                                        <flux:icon.pencil-square class="size-4" />
                                                    </a>
                                                    @if(auth()->id() !== $managedUser->id)
                                                        <form method="POST" action="{{ route('manage.users.toggle-active', $managedUser) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit" class="inline-flex h-9 items-center justify-center rounded-xl border border-zinc-200 bg-white px-3 text-xs font-semibold text-zinc-600 transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:text-white">
                                                                {{ $managedUser->is_active ? 'Stop' : 'Start' }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="flex flex-col gap-4 border-t border-zinc-200 px-5 py-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
                            <div>Rodoma {{ $users->firstItem() }}-{{ $users->lastItem() }} is {{ $users->total() }}</div>
                            <div>{{ $users->links() }}</div>
                        </div>
                    @else
                        <div class="p-6">
                            <x-ui.empty-state title="Vartotojų nerasta" description="Pabandykite pakeisti paiešką arba sukurkite naują vartotoją." />
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>








