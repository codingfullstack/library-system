<x-layouts::app :title="'Redaguoti biblioteką'">
    <x-ui.page>
        <x-ui.page-header eyebrow="Valdymas" title="Redaguoti biblioteką" :description="$library->name" />

        @if(session('success'))
            <x-ui.alert>{{ session('success') }}</x-ui.alert>
        @endif

        @if(session('error'))
            <x-ui.alert type="error">{{ session('error') }}</x-ui.alert>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_30rem]">
            <x-ui.panel title="Bibliotekos informacija" description="Atnaujinkite bibliotekos duomenis ir viešumo būseną.">
                <form method="POST" action="{{ route('manage.libraries.update', $library) }}">
                    @method('PUT')
                    @include('manage.libraries._form', ['submitLabel' => 'Išsaugoti pakeitimus'])
                </form>
            </x-ui.panel>

            <x-ui.panel title="Administratoriai ir darbuotojai" description="Priskirkite esamus vartotojus šiai bibliotekai.">
                <form method="POST" action="{{ route('manage.libraries.staff.store', $library) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="email" class="app-label">Vartotojo el. paštas</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" class="app-input" placeholder="vardas@elpastas.lt" required>
                        @error('email') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="role" class="app-label">Rolė bibliotekoje</label>
                        <select id="role" name="role" class="app-input" required>
                            <option value="administratorius" @selected(old('role') === 'administratorius')>Admin</option>
                            <option value="darbuotojas" @selected(old('role') === 'darbuotojas')>Darbuotojas</option>
                        </select>
                        @error('role') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="app-button-primary w-full">Priskirti vartotoją</button>
                </form>

                <div class="mt-6 divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($staffUsers as $staffUser)
                        <div class="flex items-center gap-3 py-4">
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-semibold text-zinc-950 dark:text-white">{{ $staffUser->name }}</div>
                                <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $staffUser->email }}</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ ucfirst($staffUser->role) }}</span>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $staffUser->pivot->is_active ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' }}">
                                        {{ $staffUser->pivot->is_active ? 'Aktyvus' : 'Neaktyvus' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('manage.libraries.staff.toggle', [$library, $staffUser]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-600 transition hover:text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:text-white" title="{{ $staffUser->pivot->is_active ? 'Deaktyvuoti' : 'Aktyvuoti' }}" aria-label="{{ $staffUser->pivot->is_active ? 'Deaktyvuoti' : 'Aktyvuoti' }}">
                                        @if($staffUser->pivot->is_active)
                                            <flux:icon.pause class="size-4" />
                                        @else
                                            <flux:icon.play class="size-4" />
                                        @endif
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('manage.libraries.staff.destroy', [$library, $staffUser]) }}" onsubmit="return confirm('Atskirti šį darbuotoją nuo bibliotekos?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-red-200 bg-white text-red-600 transition hover:bg-red-50 dark:border-red-900/60 dark:bg-zinc-900 dark:text-red-300 dark:hover:bg-red-950/30" title="Pašalinti" aria-label="Pašalinti">
                                        <flux:icon.trash class="size-4" />
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <x-ui.empty-state title="Dar nėra priskirtų darbuotojų" description="Įveskite vartotojo el. paštą ir pasirinkite rolę." />
                    @endforelse
                </div>
            </x-ui.panel>
        </div>
    </x-ui.page>
</x-layouts::app>








