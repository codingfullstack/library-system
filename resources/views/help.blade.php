<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php($seoTitle = 'Pagalba')
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-slate-950 antialiased dark:bg-zinc-950 dark:text-white">
        @php
            $topics = [
                ['icon' => 'magnifying-glass', 'title' => 'Knygos paieška', 'text' => 'Kataloge ieškokite pagal pavadinimą, autorių, kategoriją ar kitus filtrus.'],
                ['icon' => 'calendar-days', 'title' => 'Rezervacijos', 'text' => 'Prisijungę nariai gali rezervuoti knygas ir sekti savo rezervacijų būsenas.'],
                ['icon' => 'book-open-text', 'title' => 'Išduotos knygos', 'text' => 'Nario paskyroje matysite šiuo metu išduotas knygas, terminus ir istoriją.'],
                ['icon' => 'bell', 'title' => 'Pranešimai', 'text' => 'Sistema informuoja apie rezervacijų pokyčius, vėlavimus ir svarbius veiksmus.'],
            ];

            $faq = [
                ['question' => 'Kodėl nematau katalogo?', 'answer' => 'Katalogas šioje sistemoje pasiekiamas prisijungusiems naudotojams, nes matomumas priklauso nuo bibliotekos ir rolės teisių.'],
                ['question' => 'Kas gali išduoti arba grąžinti knygą?', 'answer' => 'Šiuos veiksmus atlieka darbuotojai, administratoriai arba superadministratoriai pagal savo bibliotekos teises.'],
                ['question' => 'Kaip veikia rezervacijų eilė?', 'answer' => 'Rezervacija priklauso knygai. Kai atsiranda laisvas egzempliorius, sistema gali aptarnauti eilę pagal aktyvias rezervacijas.'],
                ['question' => 'Kur kreiptis dėl paskyros?', 'answer' => 'Dėl paskyros duomenų, bibliotekos priskyrimo ar rolės pakeitimo kreipkitės į savo bibliotekos administratorių.'],
            ];
        @endphp

        <div class="min-h-screen bg-white dark:bg-zinc-950">
            @include('partials.public-header')

            <main>
                <section class="bg-[#f8fcfa] dark:bg-[#07110f]">
                    <div class="mx-auto grid w-full max-w-[1780px] gap-10 px-8 py-16 lg:grid-cols-[minmax(0,0.58fr)_minmax(0,0.42fr)] lg:px-12 xl:px-16">
                        <div class="max-w-3xl">
                            <p class="text-sm font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Pagalba</p>
                            <h1 class="mt-4 text-5xl font-extrabold leading-tight tracking-normal text-slate-950 md:text-6xl dark:text-white">
                                Greita pagalba naudojantis sistema
                            </h1>
                            <p class="mt-6 text-lg leading-8 text-slate-600 dark:text-zinc-300">
                                Čia rasite pagrindinius atsakymus apie katalogą, rezervacijas, išduotas knygas ir paskyros veikimą. Jei klausimas susijęs su konkrečia biblioteka, geriausia kreiptis į jos administratorių.
                            </p>
                        </div>

                        <div class="rounded-xl border border-emerald-100 bg-white p-6 shadow-sm dark:border-emerald-900/40 dark:bg-zinc-900">
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Dažniausias kelias</h2>
                            <div class="mt-5 space-y-4">
                                <div class="flex gap-4">
                                    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-sm font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">1</span>
                                    <p class="leading-7 text-slate-600 dark:text-zinc-300">Prisijunkite prie paskyros.</p>
                                </div>
                                <div class="flex gap-4">
                                    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-sm font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">2</span>
                                    <p class="leading-7 text-slate-600 dark:text-zinc-300">Atidarykite katalogą arba savo paskyros suvestinę.</p>
                                </div>
                                <div class="flex gap-4">
                                    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-sm font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">3</span>
                                    <p class="leading-7 text-slate-600 dark:text-zinc-300">Sekite rezervacijas, išdavimus ir pranešimus vienoje vietoje.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mx-auto w-full max-w-[1780px] px-8 py-10 lg:px-12 xl:px-16">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        @foreach($topics as $topic)
                            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                <span class="inline-flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                    @switch($topic['icon'])
                                        @case('calendar-days')
                                            <flux:icon.calendar-days class="size-6" />
                                            @break
                                        @case('book-open-text')
                                            <flux:icon.book-open-text class="size-6" />
                                            @break
                                        @case('bell')
                                            <flux:icon.bell class="size-6" />
                                            @break
                                        @default
                                            <flux:icon.magnifying-glass class="size-6" />
                                    @endswitch
                                </span>
                                <h2 class="mt-5 text-xl font-bold text-slate-900 dark:text-white">{{ $topic['title'] }}</h2>
                                <p class="mt-3 leading-7 text-slate-600 dark:text-zinc-300">{{ $topic['text'] }}</p>
                            </article>
                        @endforeach
                    </div>

                    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-7 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Dažniausi klausimai</h2>
                        <div class="mt-6 grid gap-4 md:grid-cols-2">
                            @foreach($faq as $item)
                                <article class="rounded-lg bg-slate-50 p-5 dark:bg-zinc-950/60">
                                    <h3 class="font-bold text-slate-900 dark:text-white">{{ $item['question'] }}</h3>
                                    <p class="mt-3 leading-7 text-slate-600 dark:text-zinc-300">{{ $item['answer'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    </section>
                </section>
            </main>

            @include('partials.public-footer')
        </div>

        @fluxScripts
    </body>
</html>







