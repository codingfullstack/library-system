<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-slate-950 antialiased dark:bg-zinc-950 dark:text-white">
        @php
            $dashboardUrl = auth()->check()
                ? (auth()->user()->role === 'narys' ? route('account.dashboard') : route('dashboard'))
                : route('login');

            $features = [
                ['icon' => 'book-open-text', 'title' => 'Platus katalogas', 'text' => 'Tūkstančiai knygų iš įvairių bibliotekų vienoje vietoje.'],
                ['icon' => 'calendar-days', 'title' => 'Patogi rezervacija', 'text' => 'Rezervuokite knygas internetu ir atsiimkite jas patogioje bibliotekoje.'],
                ['icon' => 'user', 'title' => 'Asmeninė paskyra', 'text' => 'Sekite savo skaitomų knygų, rezervacijų ir išdavimų istoriją.'],
                ['icon' => 'map-pin', 'title' => 'Daug bibliotekų', 'text' => 'Prisijunkite prie savo miesto ar rajono bibliotekos sistemos.'],
            ];

            $stats = [
                ['icon' => 'book-open-text', 'value' => number_format($publicStats['books'] ?? 0), 'label' => 'Knygų kataloge'],
                ['icon' => 'users', 'value' => number_format($publicStats['members'] ?? 0), 'label' => 'Registruotų narių'],
                ['icon' => 'building-library', 'value' => number_format($publicStats['libraries'] ?? 0), 'label' => 'Bibliotekų sistemoje'],
                ['icon' => 'calendar-days', 'value' => number_format($publicStats['activeReservations'] ?? 0), 'label' => 'Aktyvių rezervacijų'],
            ];
        @endphp

        <div class="min-h-screen bg-white dark:bg-zinc-950">
            @include('partials.public-header')

            <main>
                <section class="relative isolate overflow-hidden bg-[#f8fcfa] dark:bg-[#07110f]">
                    <picture class="absolute inset-y-0 right-0 hidden w-[46%] lg:block">
                        <source
                            srcset="
                                https://images.unsplash.com/photo-1521587760476-6c12a4b040da?auto=format&fit=crop&w=900&q=80 900w,
                                https://images.unsplash.com/photo-1521587760476-6c12a4b040da?auto=format&fit=crop&w=1400&q=85 1400w,
                                https://images.unsplash.com/photo-1521587760476-6c12a4b040da?auto=format&fit=crop&w=2000&q=85 2000w
                            "
                            sizes="46vw"
                        >
                        <img
                            src="https://images.unsplash.com/photo-1521587760476-6c12a4b040da?auto=format&fit=crop&w=1400&q=85"
                            alt="Bibliotekos skaitykla su knygų lentynomis"
                            class="h-full w-full object-cover object-center"
                            fetchpriority="high"
                        >
                    </picture>
                    <div class="absolute inset-y-0 right-0 hidden w-[46%] bg-[linear-gradient(90deg,rgba(248,252,250,0.94),rgba(248,252,250,0.22)_28%,rgba(248,252,250,0)_56%)] lg:block"></div>
                    <div class="absolute inset-y-0 left-[50%] hidden w-64 rounded-l-[100%] bg-[#f8fcfa] lg:block dark:bg-[#07110f]"></div>

                    <div class="relative mx-auto grid min-h-[400px] w-full max-w-[1780px] items-center px-8 py-12 lg:grid-cols-[minmax(0,0.54fr)_minmax(0,0.46fr)] lg:px-12 xl:px-16">
                        <div class="max-w-2xl lg:pl-0">
                            <h1 class="max-w-xl text-[52px] font-extrabold leading-[1.08] tracking-normal text-slate-950 md:text-[64px] dark:text-white">
                                Knygos jungia žmones ir idėjas
                            </h1>
                            <p class="mt-6 max-w-xl text-lg leading-8 text-slate-600 dark:text-zinc-300">
                                Bibliotekos sistema padeda lengvai atrasti knygas, rezervuoti jas ir sekti savo skaitymo istorijų kelionę.
                            </p>
                        </div>
                    </div>
                </section>

                <section id="apie" class="mx-auto w-full max-w-[1780px] px-8 py-8 lg:px-12 xl:px-16">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        @foreach($features as $feature)
                            <article class="flex min-h-28 items-center gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                <span class="inline-flex size-14 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                    @switch($feature['icon'])
                                        @case('calendar-days')
                                            <flux:icon.calendar-days class="size-7" />
                                            @break
                                        @case('user')
                                            <flux:icon.user class="size-7" />
                                            @break
                                        @case('map-pin')
                                            <flux:icon.map-pin class="size-7" />
                                            @break
                                        @default
                                            <flux:icon.book-open-text class="size-7" />
                                    @endswitch
                                </span>
                                <span>
                                    <span class="block text-lg font-bold text-slate-900 dark:text-white">{{ $feature['title'] }}</span>
                                    <span class="mt-2 block text-sm leading-6 text-slate-500 dark:text-zinc-400">{{ $feature['text'] }}</span>
                                </span>
                            </article>
                        @endforeach
                    </div>

                    <div id="bibliotekos" class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white px-4 py-5 shadow-sm sm:px-6 md:grid-cols-2 md:gap-6 md:px-8 md:py-7 xl:grid-cols-4 dark:border-zinc-800 dark:bg-zinc-900">
                        @foreach($stats as $stat)
                            <div class="flex min-w-0 items-center gap-4 rounded-lg px-1 py-2 md:px-0 md:py-0 xl:justify-center">
                                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 sm:h-14 sm:w-14 dark:bg-emerald-500/10 dark:text-emerald-300">
                                    @switch($stat['icon'])
                                        @case('users')
                                            <flux:icon.users class="size-6 sm:size-7" />
                                            @break
                                        @case('building-library')
                                            <flux:icon.building-library class="size-6 sm:size-7" />
                                            @break
                                        @case('calendar-days')
                                            <flux:icon.calendar-days class="size-6 sm:size-7" />
                                            @break
                                        @default
                                            <flux:icon.book-open-text class="size-6 sm:size-7" />
                                    @endswitch
                                </span>
                                <span class="min-w-0 text-left">
                                    <span class="block text-2xl font-bold leading-none text-emerald-700 sm:text-3xl dark:text-emerald-300">{{ $stat['value'] }}</span>
                                    <span class="mt-1 block text-sm font-semibold text-slate-600 dark:text-zinc-300">{{ $stat['label'] }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 flex flex-col gap-6 overflow-hidden rounded-xl border border-amber-200 bg-amber-50/60 px-8 py-6 md:flex-row md:items-center md:justify-between dark:border-amber-900/50 dark:bg-amber-500/10">
                        <div class="flex items-center gap-6">
                            <div class="hidden h-24 w-40 shrink-0 items-end justify-center rounded-lg bg-[linear-gradient(135deg,#f4fbf7,#fff8e6)] md:flex dark:bg-[linear-gradient(135deg,#0f2b23,#2b2412)]">
                                <div class="mb-3 flex items-end gap-2">
                                    <span class="h-16 w-7 rounded-t-full bg-emerald-700"></span>
                                    <span class="h-10 w-20 rounded-t-2xl bg-emerald-100 dark:bg-emerald-800"></span>
                                    <span class="h-20 w-8 rounded-t-full bg-emerald-600"></span>
                                </div>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Prisijunkite prie mūsų bendruomenės</h2>
                                <p class="mt-2 text-base text-slate-600 dark:text-zinc-300">Susikurkite paskyrą ir gaukite prieigą prie visų sistemos galimybių.</p>
                            </div>
                        </div>
                        <a href="{{ $dashboardUrl }}" class="inline-flex h-12 items-center justify-center gap-2 rounded-lg bg-emerald-700 px-7 text-sm font-bold text-white shadow-lg shadow-emerald-700/20 transition hover:bg-emerald-600" wire:navigate>
                            <flux:icon.user class="size-5" />
                            Prisijungti
                        </a>
                    </div>
                </section>
            </main>

            @include('partials.public-footer')
        </div>

        @fluxScripts
    </body>
</html>







