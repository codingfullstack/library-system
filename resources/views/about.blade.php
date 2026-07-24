<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.public-head')
        
    </head>
    <body class="min-h-screen bg-white text-slate-950 antialiased dark:bg-zinc-950 dark:text-white">
        @php
            $summaryStats = [
                ['value' => number_format($publicStats['libraries'] ?? 0), 'label' => 'aktyvių bibliotekų'],
                ['value' => number_format($publicStats['copies'] ?? 0), 'label' => 'kopijų fonde'],
            ];

            $sections = [
                ['icon' => 'book-open-text', 'title' => 'Katalogas ir kopijos', 'text' => 'Knygos, jų kopijos, kategorijos, leidyklos, filialai ir vietos valdomi vienoje struktūroje.'],
                ['icon' => 'calendar-days', 'title' => 'Išdavimai ir rezervacijos', 'text' => 'Sistema palaiko išdavimus, grąžinimus, rezervacijų eiles ir FIFO taisykles.'],
                ['icon' => 'users', 'title' => 'Rolės ir matomumas', 'text' => 'Superadministratorius, administratorius, darbuotojas ir narys mato tik tai, kas jiems aktualu pagal bibliotekos teises.'],
                ['icon' => 'clipboard-document', 'title' => 'Istorija ir ataskaitos', 'text' => 'Veiksmų istorija, dashboard metrikos, eksportai ir filtrai padeda sekti bibliotekos darbą.'],
            ];

            $workflow = [
                'Narys suranda knygą kataloge ir pateikia rezervaciją.',
                'Darbuotojas mato eilę, patikrina kopiją ir išduoda knygą.',
                'Grąžinimo metu atnaujinamas kopijos statusas ir rezervacijų eilė.',
                'Svarbūs veiksmai įrašomi į audit log istoriją.',
            ];
        @endphp

        <div class="min-h-screen bg-white dark:bg-zinc-950">
            @include('partials.public-header')

            <main>
                <section class="bg-[#f8fcfa] dark:bg-[#07110f]">
                    <div class="mx-auto grid w-full max-w-[1780px] gap-10 px-8 py-16 lg:grid-cols-[minmax(0,0.56fr)_minmax(0,0.44fr)] lg:px-12 xl:px-16">
                        <div class="max-w-3xl">
                            <p class="text-sm font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Apie sistemą</p>
                            <h1 class="mt-4 text-5xl font-extrabold leading-tight tracking-normal text-slate-950 md:text-6xl dark:text-white">
                                Pilna bibliotekos darbo valdymo aplinka
                            </h1>
                            <p class="mt-6 text-lg leading-8 text-slate-600 dark:text-zinc-300">
                                Bibliotekos sistema sujungia viešą katalogą, narių savitarną, darbuotojų įrankius ir administravimo procesus. Ji skirta kasdieniam bibliotekos darbui, kuriame svarbu greitai rasti informaciją, aiškiai valdyti kopijas ir matyti veiksmų istoriją.
                            </p>
                        </div>

                        <div class="rounded-xl border border-emerald-100 bg-white p-6 shadow-sm dark:border-emerald-900/40 dark:bg-zinc-900">
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Pagrindinė kryptis</h2>
                            <p class="mt-3 leading-7 text-slate-600 dark:text-zinc-300">
                                Sistema sukurta taip, kad administravimas ir realūs bibliotekos srautai būtų vienoje vietoje: nuo knygos sukūrimo iki išdavimo, grąžinimo, rezervacijos ir audito įrašo.
                            </p>
                            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                @foreach($summaryStats as $stat)
                                    <div class="rounded-lg bg-emerald-50 p-4 dark:bg-emerald-500/10">
                                        <span class="block text-3xl font-extrabold text-emerald-700 dark:text-emerald-300">{{ $stat['value'] }}</span>
                                        <span class="mt-1 block text-sm font-semibold text-slate-600 dark:text-zinc-300">{{ $stat['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mx-auto w-full max-w-[1780px] px-8 py-10 lg:px-12 xl:px-16">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        @foreach($sections as $section)
                            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                <span class="inline-flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                    @switch($section['icon'])
                                        @case('calendar-days')
                                            <flux:icon.calendar-days class="size-6" />
                                            @break
                                        @case('users')
                                            <flux:icon.users class="size-6" />
                                            @break
                                        @case('clipboard-document')
                                            <flux:icon.clipboard-document class="size-6" />
                                            @break
                                        @default
                                            <flux:icon.book-open-text class="size-6" />
                                    @endswitch
                                </span>
                                <h2 class="mt-5 text-xl font-bold text-slate-900 dark:text-white">{{ $section['title'] }}</h2>
                                <p class="mt-3 leading-7 text-slate-600 dark:text-zinc-300">{{ $section['text'] }}</p>
                            </article>
                        @endforeach
                    </div>

                    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,0.42fr)_minmax(0,0.58fr)]">
                        <section class="rounded-xl border border-slate-200 bg-white p-7 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Kam skirta</h2>
                            <p class="mt-4 leading-7 text-slate-600 dark:text-zinc-300">
                                Viešas katalogas ir nario paskyra padeda skaitytojui sekti savo knygas, o darbuotojų ir administratorių aplinka leidžia tvarkyti bibliotekos fondą, vartotojus, importus, ataskaitas ir veiksmų istoriją.
                            </p>
                        </section>

                        <section class="rounded-xl border border-slate-200 bg-white p-7 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Tipinis darbo srautas</h2>
                            <div class="mt-5 grid gap-3 md:grid-cols-2">
                                @foreach($workflow as $index => $item)
                                    <div class="rounded-lg bg-slate-50 p-4 dark:bg-zinc-950/60">
                                        <span class="text-sm font-bold text-emerald-700 dark:text-emerald-300">{{ $index + 1 }}</span>
                                        <p class="mt-2 leading-7 text-slate-700 dark:text-zinc-300">{{ $item }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    </div>
                </section>
            </main>

            @include('partials.public-footer')
        </div>

    </body>
</html>







