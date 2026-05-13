<x-layouts::app :title="'Profilis'">
    <x-ui.page class="max-w-none px-4 py-0 sm:px-6 lg:px-8">
        <div class="bg-[#f7f8fa] py-8 dark:bg-zinc-950">
            <div class="mx-auto max-w-[1500px] space-y-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight text-zinc-950 dark:text-white">Profilis</h1>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">Asmeniniai duomenys, narystė ir bibliotekos, prie kurių esi prisijungęs.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('profile.edit') }}" class="inline-flex h-11 items-center gap-2 rounded-2xl bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                            <flux:icon.cog-6-tooth class="size-4" />
                            Redaguoti nustatymuose
                        </a>
                    </div>
                </div>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex flex-col gap-5 p-6 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center gap-4">
                            <span class="inline-flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-xl font-bold text-white shadow-sm">
                                {{ $member->initials() }}
                            </span>
                            <div>
                                <h2 class="text-2xl font-bold text-zinc-950 dark:text-white">{{ $member->name }}</h2>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $member->email }}</p>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-zinc-50 px-4 py-3 ring-1 ring-zinc-200 dark:bg-zinc-950/50 dark:ring-zinc-800">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Nario numeris</div>
                            <div class="mt-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $member->membership_number ?: '-' }}</div>
                        </div>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-3">
                    <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900 xl:col-span-2">
                        <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Asmeninė informacija</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Pagrindiniai tavo paskyros kontaktai.</p>
                        </div>
                        <div class="grid gap-3 p-5 md:grid-cols-2">
                            @foreach([
                                'Vardas ir pavardė' => $member->name,
                                'El. paštas' => $member->email,
                                'Telefonas' => $member->phone ?: '-',
                                'Paskyros būsena' => $member->is_active ? 'Aktyvi' : 'Neaktyvi',
                            ] as $label => $value)
                                <div class="rounded-lg bg-zinc-50 p-3 ring-1 ring-zinc-200 dark:bg-zinc-950/50 dark:ring-zinc-800">
                                    <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</div>
                                    <div class="mt-1 text-sm font-medium text-zinc-950 dark:text-white">{{ $value }}</div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                            <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Mano QR kodas</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Parodykite jį bibliotekos darbuotojui.</p>
                        </div>
                        <div class="p-5">
                            @if($member->membership_number)
                                <div class="mx-auto flex max-w-[220px] items-center justify-center rounded-xl bg-white p-4 ring-1 ring-zinc-200 dark:ring-zinc-800">
                                    <img src="{{ route('account.profile.qr') }}" alt="Vartotojo QR kodas" class="h-44 w-44">
                                </div>
                                <p class="mt-3 break-all text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ $member->membership_number }}</p>
                            @else
                                <x-ui.empty-state title="QR kodo nėra" description="Nario numeris dar nesugeneruotas." />
                            @endif
                        </div>
                    </section>
                </div>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Nustatymai</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Prisijungimo ir paskyros valdymas.</p>
                    </div>
                    <div class="grid gap-3 p-5 md:grid-cols-2">
                        <a href="{{ route('profile.edit') }}" class="flex items-center justify-between rounded-lg bg-zinc-50 p-3 text-sm font-semibold text-zinc-950 ring-1 ring-zinc-200 transition hover:bg-zinc-100 dark:bg-zinc-950/50 dark:text-white dark:ring-zinc-800 dark:hover:bg-zinc-800">
                            <span>Redaguoti profilį</span>
                            <flux:icon.arrow-right class="size-4 text-zinc-400" />
                        </a>
                        <a href="{{ route('security.edit') }}" class="flex items-center justify-between rounded-lg bg-zinc-50 p-3 text-sm font-semibold text-zinc-950 ring-1 ring-zinc-200 transition hover:bg-zinc-100 dark:bg-zinc-950/50 dark:text-white dark:ring-zinc-800 dark:hover:bg-zinc-800">
                            <span>Keisti slaptažodį</span>
                            <flux:icon.arrow-right class="size-4 text-zinc-400" />
                        </a>
                    </div>
                </section>

                <section class="overflow-hidden rounded-[24px] border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Mano bibliotekos</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Bibliotekos, prie kurių esi prisijungęs kaip narys.</p>
                    </div>
                    <div class="p-5">
                        @if($libraries->isEmpty())
                            <x-ui.empty-state title="Bibliotekų nėra" description="Prisijungus prie viešosios bibliotekos, ji bus matoma čia." />
                        @else
                            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                @foreach($libraries as $userLibrary)
                                    <div class="rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200 dark:bg-zinc-950/50 dark:ring-zinc-800">
                                        <div class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $userLibrary->name }}</div>
                                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                            {{ collect([$userLibrary->address, $userLibrary->city])->filter()->join(', ') ?: 'Adresas nenurodytas' }}
                                        </div>
                                        <div class="mt-3 space-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            <div>{{ $userLibrary->email ?: 'El. paštas nenurodytas' }}</div>
                                            <div>{{ $userLibrary->phone ?: 'Telefonas nenurodytas' }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </x-ui.page>
</x-layouts::app>







